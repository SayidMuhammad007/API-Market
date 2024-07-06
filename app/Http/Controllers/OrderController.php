<?php

namespace App\Http\Controllers;

use App\Models\Basket;
use App\Models\Order;
use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $type = 'all')
    {
        if ($type == 'customer') {
            $query = Order::where('branch_id', auth()->user()->branch_id)->with(['customer', 'user'])->whereNotNull('customer_id')->where('status', 0)->orderBy('id', 'desc');
        } else if ($type == 'company') {
            $query = Order::where('branch_id', auth()->user()->branch_id)->with(['company', 'user'])->whereNotNull('company_id')->where('status', 0)->orderBy('id', 'desc');
        } else {
            $query = Order::where('branch_id', auth()->user()->branch_id)->with(['customer', 'company', 'user'])->where('status', 0)->orderBy('id', 'desc');
        }

        // Check if search query parameter is provided
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->where('id', 'like', "%$searchTerm%");
                $query->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                    $customerQuery->where('name', 'like', "%$searchTerm%");
                });
            });
        }

        // Paginate the results
        $orders = $query->paginate(20);

        return response()->json($orders);
    }

    public function selled(Request $request)
    {
        $query = Basket::whereHas('order', function ($query) {
            $query->where('branch_id', auth()->user()->branch_id);
        })->with(['store', 'order', 'customer', 'user', 'basket_price'])->where('status', 1)->orderBy('id', 'desc');

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->orWhereHas('store', function ($storeQuery) use ($searchTerm) {
                    $storeQuery->where('name', 'like', "%$searchTerm%");
                })
                    ->orWhereHas('order.customer', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('name', 'like', "%$searchTerm%");
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($searchTerm) {
                        $orderQuery->where('id', 'like', "%$searchTerm%");
                    });
            });
        }
        // Paginate the results
        $orders = $query->paginate(20);

        return response()->json($orders);
    }


    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $orderController = new OrderController();
        list($data, $dollar, $sum, $reduced_price, $reduced_price_type) = $orderController->showOrderData($order);
        return response()->json([
            'data' => $data,
            'total' => [
                'dollar' => $dollar,
                'sum' => $sum,
                "reduced_price" => $reduced_price,
                "reduced_price_type" => $reduced_price_type,
            ],
        ]);
    }

    public function waitingOrders()
    {
        return response()->json(auth()->user()->orders()->with(['customer', 'user'])->where('status', 2)->orderBy('id', 'desc')->get());
    }

    public function waitingOrder(Order $order)
    {
        if ($order->status == 2) {
            list($data, $dollar, $sum) = $this->showOrderData($order);
            return response()->json([
                'data' => $data,
                'total' => [
                    'dollar' => $dollar,
                    'sum' => $sum,
                ],
            ]);
        }
        return response()->json([
            'error' => 'Order not found'
        ], 404);
    }

    public function showOrderData($order)
    {
        // Fetch the sum of total prices for price_id 1 (local currency) and price_id 2 (dollars)
        $result = DB::table('order_prices')
            ->selectRaw('price_id, SUM(price) as total')
            ->where('order_id', $order->id)
            ->where('type_id', "!=", 5)
            ->whereIn('price_id', [1, 2])
            ->groupBy('price_id')
            ->get();

        $reduced_price_data = DB::table('order_prices')
            ->selectRaw('price_id, SUM(CASE WHEN type_id = 5 THEN price ELSE 0 END) as total')
            ->where('order_id', $order->id)
            ->groupBy('price_id')
            ->first();

        $reduced_price = $reduced_price_data->total ?? 0;
        $reduced_price_type = $reduced_price_data->price_id ?? null;


        $sumTotal = 0;
        $dollarTotal = 0;
        $dollarRate = Price::where('id', 2)->value('value');

        foreach ($result as $item) {
            if ($item->price_id == 1) {
                $sumTotal = $item->total;
            } elseif ($item->price_id == 2) {
                $dollarTotal = $item->total;
            }
        }

        // Adjust totals based on exchange rates if necessary
        if ($dollarTotal < 0) {
            $sumTotal += $dollarTotal * $dollarRate;
            $dollarTotal = 0;
        } elseif ($sumTotal < 0) {
            $dollarTotal += $sumTotal / $dollarRate;
            $sumTotal = 0;
        }
        if ($reduced_price_type == 1) {
            $dollarTotal -= $reduced_price;
        } else if ($reduced_price_type == 2) {
            $sumTotal -= $reduced_price;
        }
        // Load related data and return along with calculated totals
        return [$order->load(['customer', 'order_price', 'user', 'baskets', 'baskets.store', 'baskets.store.category', 'baskets.basket_price']), $dollarTotal, $sumTotal, $reduced_price, $reduced_price_type];
    }
}
