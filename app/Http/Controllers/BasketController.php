<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBasketRequest;
use App\Http\Requests\FinishOrderRequest;
use App\Http\Requests\StoreBasketRequest;
use App\Http\Requests\UpdateBasketRequest;
use App\Models\Basket;
use App\Models\BasketPrice;
use App\Models\Customer;
use App\Models\CustomerLog;
use App\Models\Order;
use App\Models\OrderPrice;
use App\Models\Price;
use App\Models\Store;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BasketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user(); // Retrieve the authenticated user
        // Calculate the values
        list($inUzs, $inDollar) = $this->calculate($user);

        // Get the basket data
        $basket = Basket::with(['basket_price', 'store', 'basket_price.price'])
            ->where('user_id', $user->id)
            ->where('status', 0)
            ->get();

        // Return the response with the basket data and calculated values
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar
            ]
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBasketRequest $request)
    {
        foreach ($request->products as $item) {
            // get product
            $product = Store::where('id', $item['product_id'])->first();
            // get authenticated user
            $user = auth()->user();
            // check product exists
            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            // check quantity
            if ($item['quantity'] > $product['quantity']) {
                return response()->json(['error' => 'Insufficient stock. Available quantity: ' . $product->quantity], 400);
            }
            // decrement product quantity
            $product->quantity -= $item['quantity'];
            $product->save();
            // check basket product exists
            $basket = Basket::where('user_id', $user->id)->where('store_id', $item['product_id'])->where('status', 0)->first();
            if ($basket) {
                $basket->quantity += $item['quantity'];
                $basket->save();
            } else {
                // create basket
                $basket = Basket::create([
                    'store_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'user_id' => $user->id,
                    'status' => 0,
                ]);
            }

            // create basket price
            $sell_price = $item['agreed_price'] ?? $product->price_sell;
            $data = $basket->basket_price()->where('store_id', $item['product_id'])->first();

            if ($data) {
                $data->update([
                    'agreed_price' => $sell_price,
                    'total' => $sell_price * $basket->quantity,
                ]);
            } else {
                $basket->basket_price()->create([
                    'agreed_price' => $sell_price,
                    'price_sell' => $product->price_sell,
                    'price_come' => $product->price_come,
                    'total' => $sell_price * $basket->quantity,
                    'price_id' => $product->price_id,
                    'store_id' => $item['product_id'],
                ]);
            }
        }


        list($inUzs, $inDollar) = $this->calculate($user);
        $basket = Basket::with(['basket_price', 'store', 'basket_price.price'])->where('user_id', auth()->user()->id)->where('status', 0)->get();
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function save(FinishOrderRequest $request)
    {
        // Get authenticated user
        $user = auth()->user();

        // Get calculated price
        list($inUzs, $inDollar) = $this->calculate($user);

        // Check if type exists
        $type = Type::find($request->type_id);
        if (!$type) {
            return response()->json(['error' => 'Type not found'], 404);
        }
        if ($type->id == 4) {
            $customer = Customer::find($request->customer_id);
            if (!$customer) {
                return response()->json(['error' => 'Customer not found'], 404);
            }
        }

        // Get user's open basket
        $basket = $user->baskets()->where('status', 0)->first();
        // Check price and update basket and order accordingly
        if ($request->price_id == 1 && (float)$request->price > (float)$inUzs) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient UZS'
            ], 400);
        }

        if ($request->price_id == 2 && $request->price > $inDollar) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient Dollar'
            ], 400);
        }
        // Check if basket has an associated order, if not, create a new order
        $order = $basket->order ?? Order::create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'customer_id' => $request->customer_id ?? null,
            'status' => 1,
        ]);



        // Update basket order_id
        $basket->update(['order_id' => $order->id]);

        // Add order price
        $order->order_price()->create([
            'price_id' => $request->price_id,
            'type_id' => $request->type_id,
            'price' => $request->price,
        ]);

        // add price to customer debt
        if ($request->type_id == 4) {
            CustomerLog::create([
                'branch_id' => $order->branch_id,
                'customer_id' => $request->customer_id,
                'type_id' => $request->type_id,
                'price_id' => $request->price_id,
                'price' => $request->price,
                'comment' => $request->comment ?? "",
            ]);
        }
        // Recalculate after adding order price
        list($inUzs, $inDollar) = $this->calculate($user);

        // If both UZS and USD are zero, update basket and order status
        if ($inUzs <= 0 && $inDollar <= 0) {
            $user->baskets()->where('status', '0')->update(['status' => 1]);
            $order->update(['status' => 0]);
        }

        // Get updated basket data
        $basket = $order->baskets()->with(['basket_price', 'store', 'basket_price.price'])->where('status', 0)->get();

        // Return response
        if ($basket->count() > 0) {
            return response()->json([
                'basket' => $basket,
                'calc' => [
                    'uzs' => $inUzs < 0 ? 0 : $inUzs,
                    'usd' => $inDollar < 0 ? 0 : (float)$inDollar
                ]
            ], 201);
        } else {
            return response()->json([
                'basket' => $order->baskets()->with(['basket_price', 'store', 'basket_price.price'])->where('status', 1)->get(),
            ], 201);
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
        list($inUzs, $inDollar) = $this->calculate($user);
        $basket = $user->baskets()->with(['basket_price', 'store', 'basket_price.price'])->where('status', 0)->get();
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar
            ]
        ], 201);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteBasketRequest $request)
    {
        foreach ($request->basket_ids as $basket_id) {
            // Check if basket exists
            $basket = Basket::where('id', $basket_id)->first();
            // Delete the basket
            if ($basket) {
                $basket->delete();
            }
        }
        $user = auth()->user();
        // Return updated list of baskets
        list($inUzs, $inDollar) = $this->calculate($user);
        $basket = Basket::with(['basket_price', 'store', 'basket_price.price'])
            ->where('user_id', $user->id)
            ->where('status', 0)
            ->get();
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar
            ]
        ], 201);
    }

    public function calculate($user)
    {
        // Retrieve the value of Dollar from the database
        $dollar = (float) Price::where('name', 'Dollar')->value('value');

        // Check if $dollar is zero or not set correctly
        if ($dollar === 0) {
            return [0, 0];
        }

        // Retrieve basket prices for the user
        $basketPrices = BasketPrice::whereIn('basket_id', function ($query) use ($user) {
            $query->select('id')
                ->from('baskets')
                ->where('status', 0)
                ->where('user_id', $user->id);
        })->get();
        // Calculate total sum and total dollar from basket prices
        $totalSum = $basketPrices->where('price_id', 1)->sum('total');
        $totalDollar = $basketPrices->where('price_id', 2)->sum('total');

        // Retrieve the user's order with status 1
        $order = Order::where('status', 1)
            ->where('user_id', $user->id)
            ->with('order_price')
            ->first();

        // Initialize payed_sum and payed_dollar
        $payed_sum = 0;
        $payed_dollar = 0;

        if ($order) {
            // Retrieve the sum of prices from the order
            $payed_sum = $order->order_price->where('price_id', 1)->sum('price');
            $payed_dollar = $order->order_price->where('price_id', 2)->sum('price');
        }

        // Calculate values in UZS and USD
        $inUzs = $dollar * ($totalDollar - $payed_dollar) + $totalSum - $payed_sum;
        $inDollar = (($totalSum - $payed_sum) / $dollar + $totalDollar) - $payed_dollar;
        $inDollarFormatted = number_format($inDollar, 2, '.', '');

        return [$inUzs, $inDollarFormatted];
    }
}
