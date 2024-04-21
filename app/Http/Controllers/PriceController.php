<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceRequest;
use App\Models\CurrencyRate;
use App\Models\Price;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Price::paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePriceRequest $request)
    {
        Price::create($request->all());
        return response()->json(Price::paginate(20));
    }

    /**
     * Display the specified resource.
     */
    public function show(Price $price)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Price $price)
    {
        // Update the last CurrencyRate finish timestamp
        $last = CurrencyRate::where('finish', '')->first();
        if ($last) {
            $last->update(['finish' => now()]);
        }

        // Update the Price model
        $price->update($request->all());

        // Create a new CurrencyRate entry
        CurrencyRate::create([
            'start' => now(),
            'finish' => null, // Assuming finish remains null until the next rate is set
            'price' => $request->price,
        ]);

        // Return a JSON response with paginated Price data
        return response()->json(Price::paginate(20));
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Price $price)
    {
        //
    }
}
