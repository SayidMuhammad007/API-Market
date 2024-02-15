<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Category::with('branch')->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        // Validate branch_id
        $access = Branch::find($request->branch_id);
        if (!$access) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid branch_id',
            ], 400);
        }

        // Create a new category
        Category::create($request->all());

        // Return success response with token
        return response()->json([
            'success' => true,
            'message' => 'Successfully created',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
