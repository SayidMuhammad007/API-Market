<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenceRequest;
use App\Models\Expence;
use App\Models\Price;
use Illuminate\Http\Request;

class ExpenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Expence::with(['type', 'price', 'user', 'branch'])->where('status', 1);

        // Check if search query parameter is provided
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->where('comment', 'like', "%$searchTerm%")
                    ->orWhere('cost', 'like', "%$searchTerm%")
                    ->orWhereHas('price', function ($priceQuery) use ($searchTerm) {
                        $priceQuery->where('name', 'like', "%$searchTerm%");
                    })
                    ->orWhereHas('type', function ($typeQuery) use ($searchTerm) {
                        $typeQuery->where('name', 'like', "%$searchTerm%");
                    });
            });
        }

        // Paginate the results
        $expences = $query->paginate(20);

        return response()->json($expences);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenceRequest $request)
    {
        $price = Price::where('id', $request->price_id)->first();
        if (!$price) {
            return response()->json([
                'message' => 'Price not found',
            ], 404);
        }

        $branchId = auth()->user()->branch->id;

        $expenceData = $request->all();
        $expenceData['branch_id'] = $branchId;
        $expenceData['user_id'] = auth()->user()->id;

        Expence::create($expenceData);

        return response()->json(Expence::with(['type', 'price', 'user', 'branch'])->where('status', 1)->paginate(20));
    }

    /**
     * Display the specified resource.
     */
    public function show(Expence $expence)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expence $expence)
    {
        $expence->update($request->all());

        return response()->json(Expence::with(['type', 'price', 'user', 'branch'])->where('status', 1)->paginate(20));
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expence $expence)
    {
        $expence->update([
            'status' => 0,
        ]);
        return response()->json(Expence::with(['type', 'price', 'user', 'branch'])->where('status', 1)->paginate(20));
    }
}
