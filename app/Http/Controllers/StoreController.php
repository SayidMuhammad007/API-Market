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
            // Add conditions to search in relevant columns
            $query->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%$searchTerm%")
                    ->orWhere('barcode', 'like', "%$searchTerm%")
                    ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                        $categoryQuery->where('name', 'like', "%$searchTerm%");
                    });
            });
        }

        // Paginate the results
        $stores = $query->orderBy("quantity", 'ASC')->paginate(10);

        return response()->json($stores);
    }


    // foreach ($stores as $store) {
    //     $result = Store::where('barcode', $store->barcode)->where('id', '!=', $store->id)->get();

    //     $otherStores = []; // Initialize an array to store other stores

    //     foreach ($result as $r) {
    //         $otherStore = [
    //             'branch_name' => $r->branch->name, // Access branch name through relationship
    //             'qty' => $r->quantity,
    //         ];

    //         $otherStores[] = $otherStore; // Add other store to the array
    //     }

    //     if (!empty($otherStores)) {
    //         $store->other_stores = $otherStores; // Assign other stores array to the store
    //     }
    // }
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
        // if (auth()->user()->id != 1) {
        //     return response()->json([
        //         'success' => 'false',
        //         'message' => 'Only admins can update'
        //     ], 401);
        // }
        // Check if the category exists
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
            $item->update(array_merge($request->all(), ['barcode' => $branch->barcode, 'branch_id' => auth()->user()->branch_id]));
            $branch->update(['barcode' => ++$branch->barcode]);
        } else {
            $item->update(array_merge($request->all()));
        }

        // Update the branch barcode

        // Optionally, handle updating the image
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Delete existing media collection if needed
            $item->clearMediaCollection('images');

            // Add the new image
            $item->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->where('status', 1)->where('branch_id', auth()->user()->branch_id)->orderBy("id", 'DESC')->paginate(20));
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
        $current_dollar = Price::where('id', 2)->value('value');
        $stores = Store::where('branch_id', $user->branch_id)->where('status', 1)->get();
        $count = $stores->count();
        $sum = $stores->where('price_id', 1)->sum(function ($store) {
            return $store->price_sell * $store->quantity;
        });
        $dollar = $stores->where('price_id', 2)->sum(function ($store) {
            return $store->price_sell * $store->quantity;
        });
        return response()->json([
            'count' => $count,
            'calculate' => [
                'sum' => $sum,
                'dollar' => $dollar,
            ],
            'total' => [
                'sum' => $sum + $dollar * $current_dollar,
                'dollar' => $dollar + $sum / $current_dollar
            ]

        ]);
    }
}
