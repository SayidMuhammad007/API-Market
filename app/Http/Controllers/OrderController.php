<?php

namespace App\Http\Controllers;

use App\Models\Basket;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = auth()->user()->orders()->with(['customer', 'user'])->where('status', 0)->orderBy('id', 'desc');

        // Check if search query parameter is provided
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                    $customerQuery->where('name', 'like', "%$searchTerm%");
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
        // Fetch the sum of total prices for price_id 1 (assuming sum is in local currency)
        $sumQuery = DB::table('order_prices')
            ->selectRaw('SUM(price) as total')
            ->where('price_id', 1)
            ->where('order_id', $order->id);


        // Fetch the sum of total prices for price_id 2 (assuming sum is in dollars)
        $dollarQuery = DB::table('order_prices')
            ->selectRaw('SUM(price) as total')
            ->where('price_id', 2)
            ->where('order_id', $order->id);

        // Execute the queries
        $sumResult = $sumQuery->first();
        $dollarResult = $dollarQuery->first();

        // Extract the total values or default to 0 if no result
        $sumTotal = $sumResult ? $sumResult->total : 0;
        $dollarTotal = $dollarResult ? $dollarResult->total : 0;

        // Return the response as JSON
        return response()->json([
            'data' => $order->load(['customer', 'user', 'baskets', 'baskets.store', 'baskets.store.category', 'baskets.basket_price']),
            'total' => [
                'dollar' => $dollarTotal,
                'sum' => $sumTotal,
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
            return response()->json($order);
        }
        return response()->json([
            'error' => 'Order not found'
        ], 404);
    }

    public function showOrderData($order)
    {
        // Fetch the sum of total prices for price_id 1 (assuming sum is in local currency)
        $sumQuery = DB::table('order_prices')
            ->selectRaw('SUM(price) as total')
            ->where('price_id', 1)
            ->where('order_id', $order->id);


        // Fetch the sum of total prices for price_id 2 (assuming sum is in dollars)
        $dollarQuery = DB::table('order_prices')
            ->selectRaw('SUM(price) as total')
            ->where('price_id', 2)
            ->where('order_id', $order->id);

        // Execute the queries
        $sumResult = $sumQuery->first();
        $dollarResult = $dollarQuery->first();

        // Extract the total values or default to 0 if no result
        $sumTotal = $sumResult ? $sumResult->total : 0;
        $dollarTotal = $dollarResult ? $dollarResult->total : 0;
        return [$order->load(['customer', 'user', 'baskets', 'baskets.store', 'baskets.store.category', 'baskets.basket_price']), $dollarTotal, $sumTotal];
        // Return the response as JSON
        
    }
}
