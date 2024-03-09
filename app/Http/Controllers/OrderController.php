<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(auth()->user()->orders()->orderBy('id', 'desc')->paginate(20));
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return response()->json($order->with('baskets')->orderBy('id', 'asc')->paginate(20));
    }
}
