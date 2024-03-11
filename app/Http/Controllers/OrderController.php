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
        $total = DB::select('select SUM(total)as total from basket_prices where basket_id = (SELECT id FROM baskets WHERE order_id=?)', [$order->id]);
        return response()->json([
            'data' => $order->with(['customer', 'user', 'baskets', 'baskets.store', 'baskets.basket_price'])->orderBy('id', 'asc')->get(),
            'total' => $total[0]->total,
        ]);
    }
}
