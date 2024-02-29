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
        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->paginate(1000));
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
    public function update(Request $request, Store $item)
    {
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
            $item->update(array_merge($request->all(), ['barcode' => $branch->barcode]));
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

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        $store->delete();
        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->paginate(1000));
    }
}
