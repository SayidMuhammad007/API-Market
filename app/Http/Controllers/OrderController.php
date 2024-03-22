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
    public function index(Request $request)
    {
        $query = auth()->user()->orders()->with(['customer', 'user'])->where('status', 0)->orderBy('id', 'desc');

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

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $orderController = new OrderController();
        list($data, $dollar, $sum) = $orderController->showOrderData($order);
        return response()->json([
            'data' => $data,
            'total' => [
                'dollar' => $dollar,
                'sum' => $sum,
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
        // Fetch the sum of total prices for price_id 1 (local currency) and price_id 2 (dollars)
        $result = DB::table('order_prices')
            ->selectRaw('price_id, SUM(price) as total')
            ->where('order_id', $order->id)
            ->whereIn('price_id', [1, 2])
            ->groupBy('price_id')
            ->get();

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

        // Load related data and return along with calculated totals
        return [$order->load(['customer', 'user', 'baskets', 'baskets.store', 'baskets.store.category', 'baskets.basket_price']), $dollarTotal, $sumTotal];
    }
}
