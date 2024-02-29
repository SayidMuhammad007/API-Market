<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinishOrderRequest;
use App\Http\Requests\StoreBasketRequest;
use App\Http\Requests\UpdateBasketRequest;
use App\Models\Basket;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;

class BasketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Basket::with('basket_price')->where('user_id', auth()->user()->id)->where('status', 0)->get());
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
        // decrement product quantity
        $product->quantity -= $request->quantity;
        $product->save();
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
        $sell_price = $request->agreed_price ?? $product->price_sell;
        $basket->basket_price()->create([
            'agreed_price' => $sell_price,
            'price_sell' => $product->price_sell,
            'price_come' => $product->price_come,
            'total' => $sell_price * $request->quantity,
            'price_id' => $product->price_id,
        ]);

        $basket = Basket::with(['basket_price', 'store'])->where('user_id', auth()->user()->id)->where('status', 0)->get();
        return response()->json($basket, 201);
    }

    /**
     * Display the specified resource.
     */
    public function save(FinishOrderRequest $request, Basket $basket)
    {
        $user = auth()->user();
        $order = Order::create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'customer_id' => $request->customer_id,
            'status' => 1,
        ]);
        foreach ($request->prices as $price) {
            $order->order_price->create([
                'order_id' => $order->id,
                'price_id' => $price['price_id'],
                'type_id' => $price['type_id'],
                'price' => $price['price'],
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBasketRequest $request)
    {
        $user = auth()->user();

        $basket = Basket::with('basket_price')->where('user_id', $user->id)
            ->where('status', 0)
            ->where('store_id', $request->product_id)
            ->first();

        if (!$basket) {
            return response()->json(['error' => 'Basket not found'], 404);
        }

        $product = Store::find($request->product_id);

        // decrement product quantity
        $product->quantity = $product->quantity + $basket->quantity - $request->quantity;
        $product->save();

        // update basket
        $basket->quantity = $request->quantity;
        $basket->save();

        $basket->basket_price()->updateOrCreate([], [
            'agreed_price' => $request->agreed_price,
            'price_sell' => $product->price_sell,
            'price_come' => $product->price_come,
            'total' => $request->agreed_price * $request->quantity,
            'price_id' => $request->price_id,
        ]);

        return response()->json($user->baskets()->with(['basket_price', 'store'])->where('status', 0)->get());
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Basket $basket)
    {
        // Check if basket exists
        if (!$basket) {
            return response()->json(['error' => 'Basket not found'], 404);
        }

        // Delete the basket
        $basket->delete();

        // Return updated list of baskets
        $baskets = Basket::with(['basket_price', 'store'])
            ->where('user_id', auth()->user()->id)
            ->where('status', 0)
            ->get();

        return response()->json($baskets);
    }
}
