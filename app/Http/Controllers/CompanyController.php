<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Company::with('branch')->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request)
    {
        // Check if the branch exists
        if (!Branch::where('id', $request->branch_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid branch_id',
            ], 400);
        }

        // Check if the company with the given phone number already exists
        if (Company::where('phone', $request->phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already in use',
            ], 201);
        }

        // Create the company
        Company::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully'
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
