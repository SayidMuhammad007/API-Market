<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayCompanyRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Price;
use App\Models\Type;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Company::where('branch_id', $user->id);
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('name', 'like', "%$$searchTerm%");
        }

        // Paginate the results
        $customers = $query->paginate(10);
        return response()->json($customers);
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

        $user = auth()->user();
        $customers = Company::where('branch_id', $user->id)->paginate(10);
        return response()->json($customers);
    }


    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $data = $company->companyLog()->with(['branch', 'type', 'company'])->where('branch_id', auth()->user()->branch_id)->get();
        $debts = $company->companyLog()->where('type_id', 4)->sum('price');
        $payments = $company->companyLog()->where('type_id', "!=", 4)->sum('price');
        return response()->json([
            'data' => $data,
            'debts' => $debts,
            'payments' => $payments
        ]);
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
        $debts = $company->companyLog()->where('type_id', '!=', 4)->first();
        if ($debts) {
            return response()->json([
                'success' => false,
                'message' => 'Company has debts'
            ]);
        }
        $company->delete();
        $user = auth()->user();
        $companies = Company::where('branch_id', $user->id)->paginate(10);
        return response()->json($companies);
    }

    public function pay(PayCompanyRequest $request, Company $company)
    {
        // check type
        $type = Type::where('id', $request->type_id)->first();
        if (!$type) {
            return response()->json([
                'error' => 'Type not found'
            ]);
        }

        // check price
        $price = Price::where('id', $request->price_id)->first();
        if (!$price) {
            return response()->json([
                'error' => 'Price not found'
            ]);
        }

        $company->companyLog()->create([
            'type_id' => $request->type_id,
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
            'branch_id' => $company->branch_id,
        ]);

        $data = $company->companyLog()->with(['branch', 'type', 'company'])->where('branch_id', auth()->user()->branch_id)->get();
        $debts = $company->companyLog()->where('type_id', 4)->sum('price');
        $payments = $company->companyLog()->where('type_id', "!=", 4)->sum('price');
        return response()->json([
            'data' => $data,
            'debts' => $debts,
            'payments' => $payments
        ]);
    }
}
