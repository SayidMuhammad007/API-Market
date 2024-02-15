<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccessRequest;
use App\Models\Access;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Access::paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAccessRequest $request)
    {
        Access::create([
            'name' => $request->name,
        ]);
        $msg = [
            'status' => 'success',
            'msg' => 'Access added successfully'
        ];
        return response()->json($msg);
    }

    /**
     * Display the specified resource.
     */
    public function show(Access $access)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Access $access)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Access $access)
    {
        //
    }
}
