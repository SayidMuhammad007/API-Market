<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Price;
use App\Models\Type;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Customer::where('branch_id', $user->id);
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
    public function store(StoreCustomerRequest $request)
    {
        $branch = Branch::where('id', $request->branch_id)->first();
        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found',
            ], 404);
        }
        Customer::create($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Customer added successfully'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $data = $customer->customerLog()->with(['branch', 'type', 'customer'])->where('branch_id', auth()->user()->branch_id)->get();
        $debts = $customer->customerLog()->where('type_id', 4)->sum('price');
        $payments = $customer->customerLog()->where('type_id', "!=", 4)->sum('price');
        return response()->json([
            'data' => $data,
            'debts' => $debts,
            'payments' => $payments
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $debts = $customer->customerLog()->where('type_id', '!=', 4)->first();
        if ($debts) {
            return response()->json([
                'success' => false,
                'message' => 'Customer has debts'
            ]);
        }
        $customer->delete();
        $user = auth()->user();
        $customers = Customer::where('branch_id', $user->id)->paginate(10);
        return response()->json($customers);
    }

    public function pay(PayCustomerRequest $request, Customer $customer)
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

        $customer->customerLog()->create([
            'type_id' => $request->type_id,
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
            'branch_id' => $customer->branch_id,
        ]);

        $data = $customer->customerLog()->with(['branch', 'type', 'customer'])->where('branch_id', auth()->user()->branch_id)->get();
        $debts = $customer->customerLog()->where('type_id', 4)->sum('price');
        $payments = $customer->customerLog()->where('type_id', "!=", 4)->sum('price');
        return response()->json([
            'data' => $data,
            'debts' => $debts,
            'payments' => $payments
        ]);
    }
}
