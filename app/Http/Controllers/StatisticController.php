<?php

namespace App\Http\Controllers;

use App\Models\Basket;
use App\Models\BasketPrice;
use App\Models\Branch;
use App\Models\CompanyLog;
use App\Models\Customer;
use App\Models\CustomerLog;
use App\Models\Expence;
use App\Models\ForwardHistory;
use App\Models\Order;
use App\Models\OrderPrice;
use App\Models\Price;
use App\Models\ReturnedStore;
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
            (SELECT SUM(price) FROM order_prices 
            INNER JOIN orders ON order_prices.order_id = orders.id
            WHERE orders.branch_id = branches.id 
            AND DATE(order_prices.created_at) BETWEEN ? AND ? AND price_id = 1) as sell_price_uzs,
            
                 (SELECT SUM(price) FROM order_prices 
                 INNER JOIN orders ON order_prices.order_id = orders.id
                 WHERE orders.branch_id = branches.id 
                 AND DATE(order_prices.created_at) BETWEEN ? AND ? AND price_id = 2) as sell_price_usd,

                (SELECT SUM(CASE WHEN (SELECT price_id FROM stores WHERE id = basket_prices.store_id) = 1 THEN price_come 
                ELSE (price_come * orders.dollar) END) FROM basket_prices              
                 INNER JOIN baskets ON basket_prices.basket_id = baskets.id
                 INNER JOIN orders ON baskets.order_id = orders.id
                 WHERE orders.branch_id = branches.id 
                 AND DATE(basket_prices.created_at) BETWEEN ? AND ? AND price_id = 1) as come_price_uzs,

                 (SELECT SUM(CASE WHEN (SELECT price_id FROM stores WHERE id = basket_prices.store_id) = 2 THEN price_come 
                ELSE (price_come / orders.dollar) END) FROM basket_prices      
                 INNER JOIN baskets ON basket_prices.basket_id = baskets.id
                 INNER JOIN orders ON baskets.order_id = orders.id
                 WHERE orders.branch_id = branches.id 
                 AND DATE(basket_prices.created_at) BETWEEN ? AND ? AND price_id = 2) as come_price_usd
                 ')
                ->setBindings([$start, $finish, $start, $finish, $start, $finish, $start, $finish])
                ->get();

            return response()->json([
                'start' => $start,
                'finish' => $finish,
                'data' => $branches
            ]);
        } else {
            return response()->json([
                'success' => false,
                'msg' => 'Iltimos sanani tanlang!'
            ]);
        }
    }

    public function tradeStat($start = null, $finish = null)
    {
        if ($start != null && $finish != null) {
            $branches = Branch::selectRaw('id, name, 
            ((SELECT SUM(price) FROM order_prices 
                  INNER JOIN orders ON order_prices.order_id = orders.id
                  WHERE orders.branch_id = branches.id AND DATE(order_prices.created_at) BETWEEN ? AND ?  AND price_id = 1) -  
                 (SELECT IFNULL(SUM(CASE WHEN (SELECT price_id FROM stores WHERE id = baskets.store_id) = 1 THEN stores.price_come
                 ELSE (stores.price_come * baskets.quantity * orders.dollar) END), 0) FROM order_prices 
                  INNER JOIN orders ON order_prices.order_id = orders.id
                  INNER JOIN baskets ON orders.id = baskets.order_id
                  INNER JOIN stores ON baskets.store_id = stores.id
                  WHERE orders.branch_id = branches.id AND DATE(orders.created_at) BETWEEN ? AND ?  AND order_prices.price_id = 1)
                ) as benefit_uzs,

                ((SELECT SUM(price) FROM order_prices 
                INNER JOIN orders ON order_prices.order_id = orders.id
                WHERE orders.branch_id = branches.id AND DATE(order_prices.created_at) BETWEEN ? AND ?  AND price_id = 2) -  
               (SELECT IFNULL(SUM(CASE WHEN (SELECT price_id FROM stores WHERE id = baskets.store_id) = 2 THEN stores.price_come
               ELSE (stores.price_come  * baskets.quantity / orders.dollar) END), 0) FROM order_prices 
                INNER JOIN orders ON order_prices.order_id = orders.id
                INNER JOIN baskets ON orders.id = baskets.order_id
                INNER JOIN stores ON baskets.store_id = stores.id
                WHERE orders.branch_id = branches.id AND DATE(orders.created_at) BETWEEN ? AND ?  AND order_prices.price_id = 2)
              ) as benefit_usd
                 ')
                ->setBindings([$start, $finish, $start, $finish, $start, $finish, $start, $finish])
                ->get();
            foreach ($branches as $branch) {
                $selled_uzs = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 1)
                    ->where('type_id', '!=', 5)
                    ->sum('price');
                $selled_usd = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 2)
                    ->where('type_id', '!=', 5)
                    ->sum('price');

                $selled_naqd = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 1)
                    ->where('type_id', '=', 1)
                    ->sum('price');
                $selled_click = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 1)
                    ->where('type_id', '=', 3)
                    ->sum('price');
                $selled_plastik = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 1)
                    ->where('type_id', '=', 2)
                    ->sum('price');
                $selled_back_uzs = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 1)
                    ->where('type_id', '=', 5)
                    ->sum('price');
                $sell_price_back_usd = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 2)
                    ->where('type_id', '=', 5)
                    ->sum('price');
                $sell_price_nasiya_usd = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 2)
                    ->where('type_id', '=', 4)
                    ->sum('price');
                $sell_price_nasiya_uzs = OrderPrice::whereIn('order_id', Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->pluck('id'))
                    ->where('price_id', 1)
                    ->where('type_id', '=', 4)
                    ->sum('price');
                $vozvrat_uzs = ReturnedStore::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 1)
                    ->sum('cost');
                $vozvrat_usd = ReturnedStore::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 2)
                    ->sum('cost');
                $tovar_oldik_uzs = ForwardHistory::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 1)
                    ->sum('price_come');
                $tovar_oldik_usd = ForwardHistory::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 2)
                    ->sum('price_come');
                $customer_payment_uzs = CustomerLog::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 1)
                    ->where('type_id', '!=', 4)
                    ->sum('price');
                $customer_payment_usd = CustomerLog::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 2)
                    ->where('type_id', '!=', 4)
                    ->sum('price');
                $to_company_payment_uzs = CompanyLog::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 1)
                    ->where('type_id', '!=', 4)
                    ->sum('price');
                $to_company_payment_usd = CompanyLog::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 2)
                    ->where('type_id', '!=', 4)
                    ->sum('price');
                $expence_uzs = Expence::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 1)
                    ->sum('cost');
                $expence_usd = Expence::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->where('price_id', 2)
                    ->sum('cost');
                $orders = Order::where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$start, $finish])
                    ->with(['order_price', 'baskets'])
                    ->get();

                $benefit_uzs = 0;
                $benefit_usd = 0;

                foreach ($orders as $order) {
                    // Sum order_price for UZS and USD
                    $benefit_uzs += $order->order_price->where('price_id', 1)->sum('price');
                    $benefit_usd += $order->order_price->where('price_id', 2)->sum('price');

                    // Sum basket prices for UZS and USD
                    foreach ($order->baskets as $basket) {
                        foreach ($basket->basket_price as $price) {
                            $store_price = Store::where('id', $price->store_id)->first();
                            // return response()->json($store_price);
                            if ($price && $store_price->price_id == 2) {
                                $benefit_usd -= $store_price->price_come * $basket->quantity;
                            } else if ($price && $store_price->price_id == 1) {
                                // return response()->json([$basket->quantity, $store_price]);
                                $benefit_uzs -= $price->store_price * $basket->quantity;
                            }
                        }
                    }
                }
                $dollarValues = $orders->pluck('dollar'); // Extract 'dollar' values from orders
                $dollarAverage = $dollarValues->avg(); // Calculate the average
                // if ($benefit_usd > 0) {
                    $benefit_uzs_t = $benefit_usd * $dollarAverage;
                    $benefit_uzs = $benefit_uzs - $benefit_uzs_t;
                    $benefit_usd = $benefit_uzs / $dollarAverage;
                // }
                // if ($benefit_uzs > 0) {
                //     $benefit_usd += $benefit_uzs / $dollarAverage;
                // }



                $branch['conv_usd'] = 0;
                $branch['conv_uzs'] = $selled_uzs;
                $branch['sell_price_uzs'] = $selled_uzs;
                $branch['sell_price_usd'] = $selled_usd;
                $branch['sell_price_naqd'] = $selled_naqd;
                $branch['sell_price_click'] = $selled_click;
                $branch['sell_price_plastik'] = $selled_plastik;
                $branch['sell_price_back_uzs'] = $selled_back_uzs;
                $branch['sell_price_back_usd'] = $sell_price_back_usd;
                $branch['sell_price_nasiya_usd'] = $sell_price_nasiya_usd;
                $branch['sell_price_nasiya_uzs'] = $sell_price_nasiya_uzs;
                $branch['vozvrat_uzs'] = $vozvrat_uzs;
                $branch['vozvrat_usd'] = $vozvrat_usd;
                $branch['tovar_oldik_uzs'] = $tovar_oldik_uzs;
                $branch['tovar_oldik_usd'] = $tovar_oldik_usd;
                $branch['customer_payment_uzs'] = $customer_payment_uzs;
                $branch['customer_payment_usd'] = $customer_payment_usd;
                $branch['to_company_payment_usd'] = $to_company_payment_usd;
                $branch['to_company_payment_uzs'] = $to_company_payment_uzs;
                $branch['expence_uzs'] = $expence_uzs;
                $branch['expence_usd'] = $expence_usd;
                $branch['kassa_uzs'] = $selled_uzs - $expence_uzs - $to_company_payment_uzs + $customer_payment_uzs;
                $branch['kassa_usd'] = $selled_usd - $expence_usd - $to_company_payment_usd + $customer_payment_usd;
                $branch['price_come_uzs'] = $benefit_uzs;
                $branch['price_come_usd'] = $benefit_usd;
                // $branch['test'] = $quantity_uzs;
                // $branch['quantity_usd'] = $quantity_usd;
                // $branch['price_come_usd'] = $selled_usd - $price_come_usd * $quantity_usd;
            }
            return response()->json([
                'start' => $start,
                'finish' => $finish,
                'data' => $branches
            ]);
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
