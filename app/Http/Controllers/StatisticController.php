<?php

namespace App\Http\Controllers;

use App\Models\BasketPrice;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expence;
use App\Models\Order;
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
        $month = $request->filled('month') ? $request->input('month') : 0;
        $branch_id = $request->filled('branch_id') ? $request->input('branch_id') : 0;

        // Define month names in Uzbek
        $month_names_uzbek = [
            'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun',
            'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'
        ];

        // Initialize data array
        $data = [];

        // Check if specific month is requested
        if ($month > 0) {
            $data = $this->calculateDailyData($year, $month, $price_id, $result_type, $branch_id);
        } else {
            // Calculate data for each month of the year
            for ($month = 1; $month <= 12; $month++) {
                $monthlyData = $this->calculateMonthlyData($year, $month, $price_id, $result_type, $month_names_uzbek, $branch_id);
                $data = array_merge($data, $monthlyData);
            }
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    private function calculateDailyData($year, $month, $price_id, $result_type, $branch_id)
    {
        // Initialize daily data array
        $daily_data = [];

        // Get the number of days in the specified month
        $daysInMonth = Carbon::createFromDate($year, $month)->daysInMonth;

        // Iterate through each day of the month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            // Calculate the date for the current day
            $currentDate = Carbon::createFromDate($year, $month, $day)->toDateString();

            $income_sum = Order::where('branch_id', $branch_id)
                ->whereDate('created_at', $currentDate)
                ->with(['order_price' => function ($query) use ($price_id) {
                    $query->where('price_id', $price_id);
                }])
                ->get()
                ->sum(function ($order) use ($price_id) {
                    if ($price_id == 2) {
                        return $order->order_price->sum('price') * $order->dollar;
                    }
                    return $order->order_price->sum('price');
                });



            $outgoing_sum = Expence::where('price_id', $price_id)
                ->whereDate('created_at', $currentDate)
                ->where('branch_id', $branch_id)
                ->sum('cost');

            // Determine the result based on the result_type parameter
            $result = 0;
            if ($result_type === 'income') {
                $result = $income_sum;
            } elseif ($result_type === 'outgoing') {
                $result = $outgoing_sum;
            } elseif ($result_type === 'all') {
                $agreed_price = BasketPrice::where('price_id', $price_id)
                    ->whereHas('basket.order', function ($query) use ($branch_id) {
                        $query->where('branch_id', $branch_id);
                    })
                    ->whereDate('created_at', $currentDate)
                    ->sum('agreed_price');

                $price_come = BasketPrice::where('price_id', $price_id)
                    ->whereHas('basket.order', function ($query) use ($branch_id) {
                        $query->where('branch_id', $branch_id);
                    })
                    ->whereDate('created_at', $currentDate)
                    ->sum('price_come');
                $result = $agreed_price - $price_come - $outgoing_sum;
            }

            // Add the daily data to the result array
            $daily_data[] = [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'date' => $currentDate,
                'result' => $result,
            ];
        }

        return $daily_data;
    }


    private function calculateMonthlyData($year, $month, $price_id, $result_type, $month_names_uzbek, $branch_id)
    {
        // Initialize monthly data array
        $monthly_data = [];

        // Calculate the start and end dates for the month
        $start_date = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end_date = $start_date->copy()->endOfMonth();

        // Calculate the income and outgoing sums for the month
        $income_sum = Order::where('branch_id', $branch_id)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->with(['order_price' => function ($query) use ($price_id) {
                $query->where('price_id', $price_id);
            }])
            ->get()
            ->sum(function ($order) {
                return $order->order_price->sum('price');
            });

        $outgoing_sum = Expence::where('price_id', $price_id)
            ->whereBetween('created_at', [$start_date, $end_date])
            ->where('branch_id', $branch_id)
            ->sum('cost');

        // Determine the result based on the result_type parameter
        $result = 0;
        if ($result_type === 'income') {
            $result = $income_sum;
        } elseif ($result_type === 'outgoing') {
            $result = $outgoing_sum;
        } elseif ($result_type === 'all') {
            $agreed_price = BasketPrice::where('price_id', $price_id)
                ->whereHas('basket.order', function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->whereBetween('created_at', [$start_date, $end_date])
                ->sum('agreed_price');

            $price_come = BasketPrice::where('price_id', $price_id)
                ->whereHas('basket.order', function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->whereBetween('created_at', [$start_date, $end_date])
                ->sum('price_come');
            $result = $agreed_price - $price_come - $outgoing_sum;
        }

        // Add the monthly data to the result array
        $monthly_data[] = [
            'year' => $year,
            'month' => $month,
            'month_name' => $month_names_uzbek[$month - 1], // Subtract 1 because array index starts from 0
            $result_type => $result,
        ];

        return $monthly_data;
    }


    public function branchesStat($start = null, $finish = null)
    {
        if ($start != null && $finish != null) {
            $branches = Branch::selectRaw('id, name, 
                (SELECT SUM(price) FROM avtozapchast_new.order_prices 
                 INNER JOIN avtozapchast_new.orders ON avtozapchast_new.order_prices.order_id = avtozapchast_new.orders.id
                 WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id 
                 AND DATE(avtozapchast_new.order_prices.created_at) BETWEEN ? AND ? AND price_id = 1) as sell_price_uzs,

                 (SELECT SUM(price) FROM avtozapchast_new.order_prices 
                 INNER JOIN avtozapchast_new.orders ON avtozapchast_new.order_prices.order_id = avtozapchast_new.orders.id
                 WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id 
                 AND DATE(avtozapchast_new.order_prices.created_at) BETWEEN ? AND ? AND price_id = 2) as sell_price_usd,

                (SELECT SUM(price_come) FROM avtozapchast_new.basket_prices 
                 INNER JOIN avtozapchast_new.baskets ON avtozapchast_new.basket_prices.basket_id = avtozapchast_new.baskets.id
                 INNER JOIN avtozapchast_new.orders ON avtozapchast_new.baskets.order_id = avtozapchast_new.orders.id
                 WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id 
                 AND DATE(avtozapchast_new.basket_prices.created_at) BETWEEN ? AND ? AND price_id = 1) as come_price_uzs,

                 (SELECT SUM(price_come) FROM avtozapchast_new.basket_prices 
                 INNER JOIN avtozapchast_new.baskets ON avtozapchast_new.basket_prices.basket_id = avtozapchast_new.baskets.id
                 INNER JOIN avtozapchast_new.orders ON avtozapchast_new.baskets.order_id = avtozapchast_new.orders.id
                 WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id 
                 AND DATE(avtozapchast_new.basket_prices.created_at) BETWEEN ? AND ? AND price_id = 2) as come_price_usd,

                ((SELECT SUM(price) FROM avtozapchast_new.order_prices 
                  INNER JOIN avtozapchast_new.orders ON avtozapchast_new.order_prices.order_id = avtozapchast_new.orders.id
                  WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id AND DATE(avtozapchast_new.order_prices.created_at) BETWEEN ? AND ?  AND price_id = 1) -  
                 (SELECT SUM(price_come) FROM avtozapchast_new.basket_prices 
                  INNER JOIN avtozapchast_new.baskets ON avtozapchast_new.basket_prices.basket_id = avtozapchast_new.baskets.id
                  INNER JOIN avtozapchast_new.orders ON avtozapchast_new.baskets.order_id = avtozapchast_new.orders.id
                  WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id AND DATE(avtozapchast_new.basket_prices.created_at) BETWEEN ? AND ?  AND price_id = 1)
                ) as benefit_uzs,
                ((SELECT SUM(price) FROM avtozapchast_new.order_prices 
                INNER JOIN avtozapchast_new.orders ON avtozapchast_new.order_prices.order_id = avtozapchast_new.orders.id
                WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id AND DATE(avtozapchast_new.order_prices.created_at) BETWEEN ? AND ?  AND price_id = 2) -  
               (SELECT SUM(price_come) FROM avtozapchast_new.basket_prices 
                INNER JOIN avtozapchast_new.baskets ON avtozapchast_new.basket_prices.basket_id = avtozapchast_new.baskets.id
                INNER JOIN avtozapchast_new.orders ON avtozapchast_new.baskets.order_id = avtozapchast_new.orders.id
                WHERE avtozapchast_new.orders.branch_id = avtozapchast_new.branches.id AND DATE(avtozapchast_new.basket_prices.created_at) BETWEEN ? AND ?  AND price_id = 2)
              ) as benefit_usd,
                (SELECT SUM(cost) FROM avtozapchast_new.expences 
                 WHERE avtozapchast_new.expences.branch_id = avtozapchast_new.branches.id AND DATE(avtozapchast_new.expences.created_at) BETWEEN ? AND ?) as expence')
                ->setBindings([$start, $finish, $start, $finish, $start, $finish, $start, $finish, $start, $finish, $start, $finish, $start, $finish, $start, $finish])
                ->get();

            return response()->json($branches);
        } else {
            return response()->json([
                'success' => false,
                'msg' => 'Iltimos sanani tanlang!'
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get request parameters
        $date_start = $request->filled('date_start') ? $request->input('date_start') : '2023-01-01';
        $date_finish = $request->filled('date_finish') ? $request->input('date_finish') : Carbon::now()->toDateString();
        $type = $request->filled('type') ? $request->input('type') : "stores";

        if ($type == "stores") {
            // ************************ start product stat 
            // Query most sold products
            $stores_more_selled = $this->queryStores($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));

            // Query least sold products
            $stores_less_selled = $this->queryStores($date_start, $date_finish, 'asc', 10, $request->input('branch_id'));
            // ************************ end product stat 

            return response()->json([
                'stores_more_selled' => $stores_more_selled,
                'stores_less_selled' => $stores_less_selled,
            ]);
        } else if ($type == "customers") {
            // ************************ start customer stat
            $customers = $this->queryCustomers($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
            $customers_by_price = $this->queryCustomersByPrice($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
            // ************************ end customer stat

            return response()->json([
                'customers' => $customers,
                'customers_by_price' => $customers_by_price,
            ]);
        } else if ($type == "users") {
            // ************************ start user stat
            $users = $this->queryUsers($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
            $users_by_price = $this->queryUsersByPrice($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
            // ************************ end user stat


            return response()->json([
                'users' => $users,
                'users_by_price' => $users_by_price,
            ]);
        } else if ($type == "users_returned") {

            // ************************ start returned stat
            $users_returned = $this->queryReturnedByUser($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
            $users_returned_by_price = $this->queryReturnedByPrice($date_start, $date_finish, 'desc', 10, $request->input('branch_id'));
            // ************************ end returned stat

            return response()->json([
                'users_returned' => $users_returned,
                'users_returned_by_price' => $users_returned_by_price,
            ]);
        }
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
}
