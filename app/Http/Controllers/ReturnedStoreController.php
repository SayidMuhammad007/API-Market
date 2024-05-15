<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturnRequest;
use App\Models\Basket;
use App\Models\Order;
use App\Models\Price;
use App\Models\ReturnedStore;
use App\Models\Store;
use Illuminate\Http\Request;

class ReturnedStoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ReturnedStore::with(['user', 'store', 'price:name'])->where('branch_id', auth()->user()->branch_id);

        // Check if search query parameter is provided
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->where('comment', 'like', "%$searchTerm%")
                    ->orWhereHas('store', function ($storeQuery) use ($searchTerm) {
                        $storeQuery->where('name', 'like', "%$searchTerm%");
                    })
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', "%$searchTerm%");
                    });
            });
        }

        // Paginate the results
        $returneds = $query->orderBy('id', 'DESC')->paginate(20);

        return response()->json($returneds);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReturnRequest $request)
    {
        foreach ($request->data as $item) {
            $order = Order::where('id', $item['order_id'])->where('status', 0)->first();
            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }
            $basket = Basket::where('order_id', $item['order_id'])->where('store_id', $item['store_id'])->where('status', 1)->first();
            if (!$basket) {
                return response()->json(['error' => 'Basket not found'], 404);
            }
            if (!$request->price_id) {
                return response()->json(['error' => 'Price not found'], 404);
            }

            if (!$request->type_id) {
                return response()->json(['error' => 'Type not found'], 404);
            }

            $store = Store::where('id', $item['store_id'])->first();
            if (!$store) {
                return response()->json([
                    'message' => 'Product not found',
                ]);
            }
            if (!$item['quantity']) {
                return response()->json([
                    'message' => 'Quantity required',
                ]);
            }
            ReturnedStore::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'store_id' => $item['store_id'],
                'quantity' => $item['quantity'],
                'comment' => $item['comment'],
                'cost' => $basket->basket_price[0]->agreed_price,
                'price_id' => $basket->basket_price[0]->price_id,
            ]);

            $store->update([
                'quantity' => $item['quantity'] + $store->quantity,
            ]);
            if ($basket->quantity <= $item['quantity']) {
                $price = 0;
                if ($basket->basket_price[0]->price_id == 2 && $request->price_id == 1) {
                    $dollar = Price::where('id', 2)->value('value');
                    $price = $dollar * $basket->basket_price[0]->agreed_price;
                } else if ($basket->basket_price[0]->price_id == 1 && $request->price_id == 2) {
                    $dollar = Price::where('id', 2)->value('value');
                    $price = $basket->basket_price[0]->agreed_price / $dollar;
                } else {
                    $price = $basket->basket_price[0]->agreed_price;
                }
                $order->order_price()->create([
                    'price_id' => $request->price_id,
                    'type_id' => $request->type_id,
                    'price' => -$price,
                ]);
                $basket->delete();
            } else {
                $newQuantity = $basket->quantity - $item['quantity'];
                $basket->update([
                    'quantity' => $newQuantity,
                ]);
            }
            // return response()->json([$basket->basket_price, ]);  
            if ($order->baskets->count() <= 0) {
                $order->delete();
            }
        }
        if ($order) {
            $orderController = new OrderController();
            list($data, $dollar, $sum) = $orderController->showOrderData($order);
            return response()->json([
                'data' => $data,
                'total' => [
                    'dollar' => $dollar,
                    'sum' => $sum,
                ],
            ]);
        } else {
            return response()->json(['status' => true]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnedStore $returnedStore)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReturnedStore $returnedStore)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReturnedStore $returnedStore)
    {
        //
    }
}
