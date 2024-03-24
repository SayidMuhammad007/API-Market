<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expence;
use App\Models\OrderPrice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StatisticController extends Controller
{

    public function calc(Request $request)
    {
        // Get the year from the request, default to the current year if not provided
        $year = $request->filled('year') ? $request->input('year') : Carbon::now()->year;
        $price_id = $request->filled('price_id') ? $request->input('price_id') : 1;
        $result_type = $request->filled('result_type') ? $request->input('result_type') : 'income';

        // Define month names in Uzbek
        $month_names_uzbek = [
            'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun',
            'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'
        ];

        // Calculate the income and outgoing sums for each month of the year
        $monthly_data = [];

        // Iterate through each month of the year
        for ($month = 1; $month <= 12; $month++) {
            // Calculate the start and end dates for the month
            $start_date = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $end_date = $start_date->copy()->endOfMonth();

            // Calculate the income and outgoing sums for the month
            $income_sum = OrderPrice::where('price_id', $price_id)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->sum('price');

            $outgoing_sum = Expence::where('price_id', $price_id)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->sum('cost');

            // Determine the result based on the result_type parameter
            $result = 0;
            if ($result_type === 'income') {
                $result = $income_sum;
            } elseif ($result_type === 'outgoing') {
                $result = $outgoing_sum;
            } elseif ($result_type === 'all') {
                $result = $income_sum - $outgoing_sum;
            }

            // Add the monthly data to the result array
            $monthly_data[] = [
                'year' => $year,
                'month' => $month,
                'month_name' => $month_names_uzbek[$month - 1], // Subtract 1 because array index starts from 0
                $result_type => $result,
            ];
        }

        return response()->json([
            'monthly_data' => $monthly_data,
        ]);
    }





    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get request parameters
        $date_start = $request->filled('date_start') ? $request->input('date_start') : '2023-01-01';
        $date_finish = $request->filled('date_finish') ? $request->input('date_finish') : Carbon::now()->toDateString();

        // ************************ start product stat 
        // Query most sold products
        $stores_more_selled = $this->queryStores($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));

        // Query least sold products
        $stores_less_selled = $this->queryStores($date_start, $date_finish, 'asc', 10, $request->input('branch_id'));
        // ************************ end product stat 

        // ************************ start customer stat
        $customers = $this->queryCustomers($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
        $customers_by_price = $this->queryCustomersByPrice($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
        // ************************ end customer stat

        // ************************ start user stat
        $users = $this->queryUsers($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
        $users_by_price = $this->queryUsersByPrice($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
        // ************************ end user stat

        // ************************ start returned stat
        $users_returned = $this->queryReturnedByUser($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
        $users_returned_by_price = $this->queryReturnedByPrice($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
        // ************************ end returned stat

        return response()->json([
            'stores_more_selled' => $stores_more_selled,
            'stores_less_selled' => $stores_less_selled,
            'customers' => $customers,
            'customers_by_price' => $customers_by_price,
            'users' => $users,
            'users_by_price' => $users_by_price,
            'users_returned' => $users_returned,
            'users_returned_by_price' => $users_returned_by_price,
        ]);
    }

    private function queryStores($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $stores = Store::selectRaw('name, made_in, quantity, (SELECT COUNT(id) FROM baskets WHERE store_id = stores.id AND created_at BETWEEN ? AND ?) as count', [$date_start, $date_finish])
            ->orderBy('count', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $stores->where('branch_id', $branch_id);
        }

        return $stores->get();
    }

    private function queryCustomers($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $customers = Customer::selectRaw('name, (SELECT COUNT(id) FROM orders WHERE customer_id = customers.id AND created_at BETWEEN ? AND ?) as count', [$date_start, $date_finish])
            ->orderBy('count', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $customers->where('branch_id', $branch_id);
        }

        return $customers->get();
    }

    private function queryCustomersByPrice($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $customers = Customer::select('name')
            ->selectRaw('(SELECT SUM(price) FROM order_prices WHERE price_id = 1 AND order_id IN (SELECT id FROM orders WHERE customer_id = customers.id) AND created_at BETWEEN ? AND ?) as total_sum', [$date_start, $date_finish])
            ->selectRaw('(SELECT SUM(price) FROM order_prices WHERE price_id = 2 AND order_id IN (SELECT id FROM orders WHERE customer_id = customers.id) AND created_at BETWEEN ? AND ?) as total_dollar', [$date_start, $date_finish])
            ->orderBy('total_sum', $orderBy)
            ->orderBy('total_dollar', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $customers->where('branch_id', $branch_id);
        }

        return $customers->get();
    }



    // user
    private function queryUsers($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $customers = User::selectRaw('name, (SELECT COUNT(id) FROM orders WHERE user_id = users.id AND created_at BETWEEN ? AND ?) as count', [$date_start, $date_finish])
            ->orderBy('count', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $customers->where('branch_id', $branch_id);
        }

        return $customers->get();
    }

    private function queryUsersByPrice($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $customers = User::select('name')
            ->selectRaw('(SELECT SUM(price) FROM order_prices WHERE price_id = 1 AND order_id IN (SELECT id FROM orders WHERE user_id = users.id) AND created_at BETWEEN ? AND ?) as total_sum', [$date_start, $date_finish])
            ->selectRaw('(SELECT SUM(price) FROM order_prices WHERE price_id = 2 AND order_id IN (SELECT id FROM orders WHERE user_id = users.id) AND created_at BETWEEN ? AND ?) as total_dollar', [$date_start, $date_finish])
            ->orderBy('total_sum', $orderBy)
            ->orderBy('total_dollar', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $customers->where('branch_id', $branch_id);
        }

        return $customers->get();
    }

    // returned
    private function queryReturnedByUser($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $returnedByUser = User::selectRaw('name, (SELECT SUM(quantity) FROM returned_stores WHERE user_id = users.id AND created_at BETWEEN ? AND ?) as count', [$date_start, $date_finish])
            ->orderBy('count', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $returnedByUser->where('branch_id', $branch_id);
        }

        return $returnedByUser->get();
    }

    private function queryReturnedByPrice($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $returnedByUserByPrice = User::select('name')
            ->selectRaw('(SELECT SUM(cost) FROM returned_stores WHERE price_id = 1 AND user_id = users.id AND created_at BETWEEN ? AND ?) as total_sum', [$date_start, $date_finish])
            ->selectRaw('(SELECT SUM(cost) FROM returned_stores WHERE price_id = 2 AND user_id = users.id AND created_at BETWEEN ? AND ?) as total_dollar', [$date_start, $date_finish])
            ->orderBy('total_sum', $orderBy)
            ->orderBy('total_dollar', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $returnedByUserByPrice->where('branch_id', $branch_id);
        }

        return $returnedByUserByPrice->get();
    }

    private function queryCalcStat($date_start, $date_finish, $orderBy, $limit, $branch_id = null)
    {
        $returnedByUserByPrice = User::select('name')
            ->selectRaw('(SELECT SUM(cost) FROM returned_stores WHERE price_id = 1 AND user_id = users.id AND created_at BETWEEN ? AND ?) as total_sum', [$date_start, $date_finish])
            ->selectRaw('(SELECT SUM(cost) FROM returned_stores WHERE price_id = 2 AND user_id = users.id AND created_at BETWEEN ? AND ?) as total_dollar', [$date_start, $date_finish])
            ->orderBy('total_sum', $orderBy)
            ->orderBy('total_dollar', $orderBy)
            ->limit($limit);

        if ($branch_id !== null) {
            $returnedByUserByPrice->where('branch_id', $branch_id);
        }

        return $returnedByUserByPrice->get();
    }
}
