<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBasketRequest;
use App\Models\Basket;
use App\Models\Store;
use Illuminate\Http\Request;

class BasketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBasketRequest $request)
    {
        // get product
        $product = Store::where('id', $request->product_id)->first();

        // check product exists
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // check quantity
        if ($request->quantity > $product->quantity) {
            return response()->json(['error' => 'Insufficient stock. Available quantity: ' . $product->quantity], 400);
        }

        // check basket product exists
        $basket = Basket::where('user_id', auth()->user()->id)->where('store_id', $request->product_id)->where('status', 0)->first();
        if ($basket) {
            $basket->quantity += $request->quantity;
            $basket->save();
        } else {
            // create basket
            $basket = Basket::create([
                'store_id' => $request->product_id,
                'quantity' => $request->quantity,
                'user_id' => auth()->user()->id,
                'status' => 0
            ]);
        }

        // create basket price
        $sell_price =  $request->agreed_price ?? $request->agreed_price::$product->price_sell;
        $basket->basket_price()->create([
            'agreed_price' => $sell_price,
            'price_sell' => $product->price_sell,
            'price_come' => $product->price_come,
            'total' => $sell_price * $request->quantity,
            'price_id' => $product->price_id,
        ]);

        $basket = Basket::with('basket_price')->where('user_id', auth()->user()->id)->where('status', 0)->get();
        return response()->json($basket, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Basket $basket)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Basket $basket)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Basket $basket)
    {
        //
    }
}
