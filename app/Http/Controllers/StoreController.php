<?php

namespace App\Http\Controllers;

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
    public function index()
    {
        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->paginate(20));
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
        $item = Store::create(array_merge($request->all(), ['barcode' => $branch->barcode]));
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $item->addMediaFromRequest('image')->toMediaCollection('images');
        }
        $branch->update(['barcode' => ++$branch->barcode]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
        ], 201);
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
    public function update(Request $request, Store $store)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        //
    }
}
