<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTypeRequest;
use App\Models\Type;
use Illuminate\Http\Request;

class TypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Type::paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTypeRequest $request)
    {
        Type::create($request->all());
        return response()->json(Type::paginate(20));
    }

    /**
     * Display the specified resource.
     */
    public function show(Type $type)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Type $type)
    {
        $type->update($request->all());
        return response()->json(Type::paginate(20));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Type $type)
    {
        if (!$type) {
            $msg = [
                'success' => false,
                'message' => 'Type not found'
            ];
            return response()->json($msg, 404);
        }
        $type->delete();
        return response()->json(Type::paginate(20));
    }
}
