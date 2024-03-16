<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(auth()->user()->orders()->with(['customer', 'user'])->orderBy('id', 'desc')->paginate(20));
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $sum = DB::select('select SUM(total)as total from basket_prices where price_id = 1 AND basket_id = (SELECT id FROM baskets WHERE order_id=? LIMIT 1)', [$order->id]);
        $dollar = DB::select('select SUM(total)as total from basket_prices where price_id = 2 AND basket_id = (SELECT id FROM baskets WHERE order_id=? LIMIT 1)', [$order->id]);
        return response()->json([
            'data' => $order->load(['customer', 'user', 'baskets', 'baskets.store', 'baskets.store.category', 'baskets.basket_price']),
            'total' => [
                'dollar' => $dollar[0]->total,
                'sum' => $sum[0]->total ?? 0
            ],
        ]);
    }
}
