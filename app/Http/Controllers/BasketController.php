<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBasketRequest;
use App\Http\Requests\FinishOrderRequest;
use App\Http\Requests\StoreBasketRequest;
use App\Http\Requests\ToWaitingRequest;
use App\Http\Requests\UpdateBasketRequest;
use App\Models\Basket;
use App\Models\BasketPrice;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerLog;
use App\Models\Order;
use App\Models\Price;
use App\Models\Store;
use App\Models\Type;
use Carbon\Carbon;

class BasketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user(); // Retrieve the authenticated user
        // Calculate the values
        list($inUzs, $inDollar, $dollar) = $this->calculate($user);

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
                'usd' => (float)$inDollar,
                'dollar' => (float)$dollar
            ]
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBasketRequest $request)
    {
        $dollar = Price::where('id', 2)->value('value');
        foreach ($request->products as $item) {
            // get product
            $product = Store::where('id', $item['product_id'])->first();
            // get authenticated user
            $user = auth()->user();
            // check product exists
            if (!$product) {
                return response()->json(['error' => 'Mahsulot topilmadi'], 404);
            }

            // check quantity
            if ($item['quantity'] > $product['quantity']) {
                return response()->json(['error' => 'Omborda buncha mahsulot yo`q. Mavjud: ' . $product->quantity], 400);
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
                if ($product->price_id == 2) {
                    $basket->basket_price()->create([
                        'agreed_price' => $sell_price * $dollar,
                        'price_sell' => $product->price_sell * $dollar,
                        'price_come' => $product->price_come,
                        'total' => $sell_price * $dollar * $basket->quantity,
                        'price_id' => 1,
                        'qty' => $product->quantity,
                        'old_price_id' => $product->price_id,
                        'store_id' => $item['product_id'],
                    ]);
                } else {
                    $basket->basket_price()->create([
                        'agreed_price' => $sell_price,
                        'price_sell' => $product->price_sell,
                        'price_come' => $product->price_come,
                        'total' => $sell_price * $basket->quantity,
                        'price_id' => $product->price_id,
                        'qty' => $product->quantity,
                        'old_price_id' => $product->price_id,
                        'store_id' => $item['product_id'],
                    ]);
                }
            }
        }


        list($inUzs, $inDollar, $dollar) = $this->calculate($user);
        $basket = Basket::with(['basket_price', 'store', 'basket_price.price'])->where('user_id', auth()->user()->id)->where('status', 0)->get();
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar,
                'dollar' => (float)$dollar
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function save(FinishOrderRequest $request)
    {
        $user = auth()->user();
        foreach ($request->data as $item) {
            list($inUzs, $inDollar, $dollar) = $this->calculate($user);

            // Check if type exists
            $type = Type::find($item['type_id']);
            if (!$type) {
                return response()->json(['error' => 'Pul turi topilmadi'], 404);
            }
            if (isset($item['customer_id']) && $item['customer_id']) {
                $customer = Customer::find($item['customer_id']);
                if (!$customer) {
                    return response()->json(['error' => 'Mijoz topilmadi'], 404);
                }
            }
            
            if (isset($item['company_id']) && $item['company_id']) {
                $company = Company::find($item['company_id']);
                if (!$company) {
                    return response()->json(['error' => 'Firma topilmadi'], 404);
                }
            }

            // Get user's open basket
            $basket = $user->baskets()->where('status', 0)->first();

            // Check price and update basket and order accordingly
            if ($item['price_id'] == 1 && (float)$item['price'] > (float)$inUzs && !$request->price && $item['type_id'] !== 5  && $item['type_id'] !== 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'So`mda qiymat oshib ketdi'
                ], 400);
            }

            if ($item['price_id'] == 2 && $item['price'] > $inDollar && !$request->price && $item['type_id'] !== 5  && $item['type_id'] !== 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dollarda qiymat oshib ketdi'
                ], 400);
            }
            if ($item['type_id'] !== 5) {
                // Check if basket has an associated order, if not, create a new order
                $order = $basket->order ?? Order::create([
                    'branch_id' => $user->branch_id,
                    'user_id' => $user->id,
                    'dollar' => $dollar,
                    'customer_id' => $item['customer_id'] ?? null,
                    'company_id' => $item['company_id'] ?? null,
                    'status' => 1,
                    'comment' => $request->comment ?? null,
                ]);
                $basket = $user->baskets()->where('status', 0)->get();
                foreach ($basket as $test) {
                    $test->update(['order_id' => $order->id]);
                }
            }


            // Add order price
            $order->order_price()->create([
                'price_id' => $item['price_id'],
                'type_id' => $item['type_id'],
                'price' => $item['price'],
            ]);

            // add price to customer debt
            if ($item['type_id'] == 4) {
                $formattedDate = Carbon::parse($request->date)->format('Y-m-d');
                CustomerLog::create([
                    'branch_id' => $order->branch_id,
                    'customer_id' => $item['customer_id'],
                    'company_id' => $item['company_id'] ?? null,
                    'type_id' => $item['type_id'],
                    'price_id' => $item['price_id'],
                    'price' => $item['price'],
                    'comment' => $item['comment'] ?? "",
                    'date' => $formattedDate,
                ]);
            }
            // Recalculate after adding order price
            list($inUzs, $inDollar) = $this->calculate($user);

            // If both UZS and USD are zero, update basket and order status
            if ($inUzs <= 100 && $inDollar <= 0.01) {
                $user->baskets()->where('status', '0')->update(['status' => 1]);
                $order->update(['status' => 0]);
            }
        }

        if ($request->price) {
            if (!$request->price_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found'
                ], 404);
            }
            if (!$request->type_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Type not found'
                ], 404);
            }
            $order->order_price()->create([
                'price_id' => $request->price_id,
                'type_id' => $request->type_id,
                'price' => -$request->price,
            ]);
        }
        // Get updated basket data
        $basket = $order->baskets()->with(['basket_price', 'store', 'basket_price.price'])->where('status', 0)->get();

        // Return response
        if ($basket->count() > 0) {
            return response()->json([
                'basket' => $basket,
                'calc' => [
                    'uzs' => $inUzs < 0 ? 0 : $inUzs,
                    'usd' => $inDollar < 0 ? 0 : (float)$inDollar,
                    'dollar' => (float)$dollar,
                ]
            ], 201);
        } else {
            return response()->json([
                'status' => true,
                'order_id' => $order->id
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
        if ($request['quantity'] - $basket->quantity > $product['quantity'] && $request['quantity'] != $basket->quantity) {
            return response()->json(['error' => 'Omborda buncha mahsulot yo`q. Mavjud: ' . $product->quantity], 400);
        }
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
            'old_price_id' => $product->price_id,
            'total' => $request->agreed_price * $request->quantity,
            'price_id' => $request->price_id,
        ]);
        list($inUzs, $inDollar, $dollar) = $this->calculate($user);
        $basket = $user->baskets()->with(['basket_price', 'store', 'basket_price.price'])->where('status', 0)->get();
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar,
                'dollar' => (float)$dollar
            ]
        ], 201);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteBasketRequest $request)
    {
        $basketIds = $request->basket_ids;

        // Fetch all baskets with given IDs
        $baskets = Basket::whereIn('id', $basketIds)->get();

        foreach ($baskets as $basket) {
            // Update the corresponding store's quantity
            $basket->store->increment('quantity', $basket->quantity);
            // Delete the basket
            $basket->delete();
        }
        $user = auth()->user();

        // Return updated list of baskets and totals
        $basket = Basket::with(['basket_price', 'store', 'basket_price.price'])
            ->where('user_id', $user->id)
            ->where('status', 0)
            ->get();
        if ($basket->count() <= 0) {
            $user->orders()->where('status', 1)->update([
                'status' =>  0,
            ]);
        }

        // Calculate totals
        list($inUzs, $inDollar, $dollar) = $this->calculate($user);
        return response()->json([
            'basket' => $basket,
            'calc' => [
                'uzs' => $inUzs,
                'usd' => (float)$inDollar,
                'dollar' => (float)$dollar
            ]
        ], 201);
    }


    public function calculate($user)
    {
        // Retrieve the value of Dollar from the database
        $dollar = (float) Price::where('id', 2)->value('value');

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
        $totalSum = (float)$basketPrices->where('price_id', 1)->sum('total');
        $totalDollar = (float)$basketPrices->where('price_id', 2)->sum('total');

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
            $payed_sum = (float)$order->order_price->where('price_id', 1)->where('type_id', "!=", 5)->sum('price');
            $payed_dollar = (float)$order->order_price->where('price_id', 2)->where('type_id', "!=", 5)->sum('price');
        }

        // Calculate values in UZS and USD
        $inUzs = $dollar * ($totalDollar - $payed_dollar) + $totalSum - $payed_sum;
        $inDollar = (($totalSum - $payed_sum) / $dollar + $totalDollar) - $payed_dollar;
        $inDollarFormatted = number_format($inDollar, 2, '.', '');

        return [$inUzs, $inDollarFormatted, $dollar];
    }

    public function toWaiting(ToWaitingRequest $request)
    {
        $user = auth()->user();
        $basket = Basket::where('id', $request->basket_ids[0])->first();
        if ($basket) {
            $order = $basket->order ?? Order::create([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'customer_id' => $request->customer_id ?? null,
                'company_id' => $request->company_id ?? null,
                'status' => 2,
            ]);
            $order->update(['status' => 2]);
            foreach ($request->basket_ids as $item) {
                $item = Basket::where('id', $item)->first();
                $item->update([
                    'status' => 2,
                    'order_id' => $order->id,
                ]);
            }
            list($inUzs, $inDollar, $dollar) = $this->calculate($user);

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
                    'usd' => (float)$inDollar,
                    'dollar' => (float)$dollar
                ]
            ], 200);
        } else {
            return response()->json(['error' => 'Savat topilmadi'], 404);
        }
    }

    public function unwaitOrder(Order $order)
    {
        if ($order->status == 2) {
            if (auth()->user()->baskets()->where('status', 0)->count() > 0) {
                return response()->json([
                    'error' => 'Avval savatni tozalang!'
                ]);
            }
            $baskets = $order->baskets;
            $order->update([
                'status' => 1,
            ]);
            foreach ($baskets as $basket) {
                $basket->update([
                    'status' => 0,
                ]);
            }

            $user = auth()->user(); // Retrieve the authenticated user
            // Calculate the values
            list($inUzs, $inDollar, $dollar) = $this->calculate($user);

            // Get the basket data
            $basket = Basket::with(['basket_price', 'store', 'basket_price.price'])
                ->where('order_id', $order->id)
                ->get();

            // Return the response with the basket data and calculated values
            return response()->json([
                'basket' => $basket,
                'calc' => [
                    'uzs' => $inUzs,
                    'usd' => (float)$inDollar,
                    'dollar' => (float)$dollar
                ]
            ], 200);
        }
        return response()->json([
            'error' => 'Buyurtma topilmadi'
        ], 404);
    }
}
