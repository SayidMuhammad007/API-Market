<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteStoreRequest;
use App\Http\Requests\StoreRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Price;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /** 
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Store::query()
            ->with(['media', 'category', 'branch', 'price'])
            ->where('branch_id', auth()->user()->branch_id)
            ->where('status', 1);

        // Check if search query parameter is provided
        if ($request->has('search') && $request->input('search') != null) {
            $searchTerm = $request->input('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%$searchTerm%")
                    ->orWhere('barcode', 'like', "%$searchTerm%")
                    ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                        $categoryQuery->where('name', 'like', "%$searchTerm%");
                    })
                    ->orWhereHas('price', function ($priceQuery) use ($searchTerm) {
                        $priceQuery->where('name', 'like', "%$searchTerm%");
                    });
            });
        }

        // Paginate the results
        $stores = $query->orderBy("quantity", 'ASC')->paginate(10);

        return response()->json($stores);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        // check category
        $category = Category::where('id', $request->category_id)->first();
        if (!$category) {
            return response()->json([
                'success' => 'false',
                'message' => 'Category not found'
            ], 400);
        }
        // check branch
        $branch = Branch::where('id', $request->branch_id)->first();
        if (!$branch) {
            return response()->json([
                'success' => 'false',
                'message' => 'Branch not found'
            ], 400);
        }
        // check price
        $price = Price::where('id', $request->price_id)->first();
        if (!$price) {
            return response()->json([
                'success' => 'false',
                'message' => 'Price not found'
            ], 400);
        }
        $item = Store::create(array_merge($request->all(), ['barcode' => $branch->barcode, 'branch_id' => auth()->user()->branch_id]));
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $item->addMediaFromRequest('image')->toMediaCollection('images');
        }
        $item->forwardHistories()->create([
            'user_id' => auth()->user()->id,
            'branch_id' => auth()->user()->branch_id,
            'count' => $request->quantity,
            'price_come' => $request->price_come,
            'price_sell' => $request->price_sell,
            'price_id' => $request->price_id,
        ]);
        $branch->update(['barcode' => ++$branch->barcode]);

        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->where('branch_id', auth()->user()->branch_id)->where('status', 1)->orderBy("id", "DESC")->paginate(20), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $item)
    {
        if ($request->category_id) {
            $category = Category::find($request->category_id);
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 400);
            }
        }

        // Check if the branch exists
        if ($request->branch_id) {
            $branch = Branch::find($request->branch_id);
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not found'
                ], 400);
            }
        }

        // Check if the price exists
        if ($request->price_id) {
            $price = Price::find($request->price_id);
            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found'
                ], 400);
            }
        }
        // Update the item with the new data
        if ($request->branch_id) {
            $item->update(array_merge($request->all(), ['branch_id' => auth()->user()->branch_id]));
        } else {
            $item->update(array_merge($request->all()));
        }
        // Optionally, handle updating the image
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Delete existing media collection if needed
            $item->clearMediaCollection('images');

            // Add the new image
            $item->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->where('status', 1)->where('branch_id', auth()->user()->branch_id)->orderBy("id", 'DESC')->paginate(20));
    }


    public function updateQty(Request $request, Store $item)
    {
        // Check if the price exists
        if ($request->store_id) {
            $store = Store::find($request->store_id);
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 400);
            }
        }
        if (!$request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Quantity not specified'
            ], 400);
        }
        $store->update([
            'quantity' =>  $store->quantity + $request->quantity,
        ]);
        $store->forwardHistories()->create([
            'user_id' => auth()->user()->id,
            'branch_id' => auth()->user()->branch_id,
            'count' => $request->quantity,
            'price_come' => $store->price_come,
            'price_sell' => $store->price_sell,
            'price_id' => $store->price_id,
        ]);
        return response()->json(['status' => true]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeleteStoreRequest $request)
    {
        foreach ($request->stores as $store) {
            Store::where('id', $store)->update([
                'status' => '0'
            ]);
        }
        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->where('branch_id', auth()->user()->branch_id)->where('status', 1)->orderBy("id", "DESC")->paginate(20));
    }

    public function calculate()
    {
        $user = auth()->user();
        $stores = Store::where('branch_id', $user->branch_id)->where('status', 1)->get();
        $count = $stores->count();
        $sum = $stores->where('price_id', 1)->sum(function ($store) {
            return $store->price_sell * $store->quantity;
        });
        $dollar = $stores->where('price_id', 2)->sum(function ($store) {
            return $store->price_sell * $store->quantity;
        });

        $sum_come = $stores->where('price_id', 1)->sum(function ($store) {
            return $store->price_come * $store->quantity;
        });
        $dollar_come = $stores->where('price_id', 2)->sum(function ($store) {
            return $store->price_come * $store->quantity;
        });
        return response()->json([
            'count' => $count,
            'calculate' => [
                'sum' => $sum,
                'dollar' => $dollar,
            ],
            'calculate_come' => [
                'sum_come' => $sum_come,
                'dollar' => $dollar_come
            ]

        ]);
    }
}
