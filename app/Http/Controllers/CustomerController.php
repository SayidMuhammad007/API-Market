<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddDebtRequest;
use App\Http\Requests\PayCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLog;
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
        $query = Customer::where('branch_id', $user->branch_id)
            ->where('status', 1);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('name', 'like', "%$searchTerm%");
        } elseif ($request->has('id')) {
            $searchTerm = $request->input('id');
            $query->where('id', $searchTerm);
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
        list($data, $dollar, $sum) = $this->calculate($customer);
        return response()->json([
            'data' => $data,
            'debts' => [
                'all_dollar' => $dollar,
                'all_sum' => $sum,
            ],
        ]);
    }

    public function calculate($customer)
    {
        $dollar = Price::where('id', 2)->value('value');

        // Retrieve customer log data with relationships
        $data = $customer->customerLog()->with(['branch', 'type', 'customer'])
            ->where('branch_id', auth()->user()->branch_id)
            ->get();

        // Calculate total debts and payments in both currencies
        $debts_sum = $customer->customerLog()->where('type_id', 4)->where('price_id', 1)->sum('price');
        $debts_dollar = $customer->customerLog()->where('type_id', 4)->where('price_id', 2)->sum('price');
        $payments_dollar = $customer->customerLog()->where('type_id', '!=', 4)->where('price_id', 2)->sum('price');
        $payments_sum = $customer->customerLog()->where('type_id', '!=', 4)->where('price_id', 1)->sum('price');

        // Calculate total debts and payments in soums and dollars
        $total_sum = $debts_sum - $payments_sum;
        $total_dollar = $debts_dollar - $payments_dollar;

        // Convert negative totals to positive if necessary
        if ($total_sum < 0) {
            $total_dollar -= abs($total_sum) / $dollar; // Convert soums to dollars
            // return response()->json($total_dollar);
            $total_sum = 0;
        } else if ($total_dollar < 0) {
            $total_sum -= abs($total_dollar) * $dollar; // Convert dollars to soums
            $total_dollar = 0;
        }
        $all_dollar = $total_dollar + ($total_sum / $dollar);
        $all_sum = $total_sum + ($total_dollar * $dollar);
        return [$data, $all_dollar, $all_sum];
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
        // Check if the customer has debts
        $debts = $customer->customerLog()->where('type_id', 4)->exists();

        if ($debts) {
            return response()->json([
                'success' => false,
                'message' => 'Customer has debts'
            ]);
        }

        // Update the status of the customer to 0 (inactive)
        $customer->status = 0;
        $customer->save();
        // Retrieve paginated list of active customers for the current user's branch
        $user = auth()->user();
        $customers = Customer::where('branch_id', $user->branch_id)
            ->where('status', 1)
            ->paginate(10);

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
        list($data, $dollar, $sum) = $this->calculate($customer);
        return response()->json([
            'data' => $data,
            'debts' => [
                'all_dollar' => $dollar,
                'all_sum' => $sum,
            ],
        ]);
    }

    public function addDebt(Customer $customer, AddDebtRequest $request)
    {
        $type = Type::find($request->type_id);
        if (!$type) {
            return response()->json([
                'error' => 'Type not found'
            ], 404);
        }

        $price = Price::find($request->price_id);
        if (!$price) {
            return response()->json([
                'error' => 'Price not found'
            ], 404);
        }

        CustomerLog::create([
            'branch_id' => auth()->user()->branch_id,
            'customer_id' => $customer->id,
            'type_id' => 4,
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Debt added successfully'
        ]);
    }
}
