<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddStoreRequest;
use App\Http\Requests\AttachStoresRequest;
use App\Http\Requests\DebtCompanyRequest;
use App\Http\Requests\PayCompanyRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateDebtCompanyRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\Price;
use App\Models\Store;
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
        $query = Company::where('branch_id', $user->branch_id);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('name', 'like', "%$searchTerm%");
        }

        // Paginate the results
        $customers = $query->paginate($request->perPage ?? 10);

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
        $customers = Company::where('branch_id', $user->branch_id)->paginate(10);
        return response()->json($customers);
    }


    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        list($data, $debts_dollar, $debts_sum) = $this->showCompanyData($company);
        return response()->json([
            'data' => $data,
            'debts_sum' => $debts_sum,
            'debts_dollar' => $debts_dollar
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        $company->update($request->all());
        $user = auth()->user();
        $customers = Company::where('branch_id', $user->branch_id)->paginate(10);
        return response()->json($customers);
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
        foreach ($request->payments as $payment) {
            // check type
            $type = Type::where('id', $payment['type_id'])->first();
            if (!$type) {
                return response()->json([
                    'error' => 'Type not found'
                ]);
            }

            // check price
            $price = Price::where('id', $payment['price_id'])->first();
            if (!$price) {
                return response()->json([
                    'error' => 'Price not found'
                ]);
            }

            $company->companyLog()->create([
                'type_id' => $payment['type_id'],
                'price_id' => $payment['price_id'],
                'comment' => $payment['comment'],
                'price' => $payment['price'],
                'branch_id' => $company->branch_id,
            ]);
        }

        list($data, $debts_dollar, $debts_sum) = $this->showCompanyData($company);
        return response()->json([
            'data' => $data,
            'debts_sum' => $debts_sum,
            'debts_dollar' => $debts_dollar
        ]);
    }

    public function debt(DebtCompanyRequest $request, Company $company)
    {
        // check price
        $price = Price::where('id', $request->price_id)->first();
        if (!$price) {
            return response()->json([
                'error' => 'Price not found'
            ]);
        }

        $company->companyLog()->create([
            'type_id' => 4,
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
            'branch_id' => $company->branch_id,
        ]);

        list($data, $debts_dollar, $debts_sum) = $this->showCompanyData($company);
        return response()->json([
            'data' => $data,
            'debts_sum' => $debts_sum,
            'debts_dollar' => $debts_dollar
        ]);
    }

    public function updateDebt(UpdateDebtCompanyRequest $request, Company $company)
    {
        $company->companyLog()->where('id', $request->debt_id)->update([
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
            'branch_id' => $company->branch_id,
        ]);

        list($data, $debts_dollar, $debts_sum) = $this->showCompanyData($company);
        return response()->json([
            'data' => $data,
            'debts_sum' => $debts_sum,
            'debts_dollar' => $debts_dollar
        ]);
    }

    public function deleteDebt(UpdateDebtCompanyRequest $request, Company $company)
    {
        $findLog = $company->companyLog()->where('id', $request->debt_id)->first();
        if (!$findLog) {
            return response()->json([
                'status' => false,
                'error' => 'Debt not found'
            ]);
        }
        $findLog->delete();
        list($data, $debts_dollar, $debts_sum) = $this->showCompanyData($company);
        return response()->json([
            'data' => $data,
            'debts_sum' => $debts_sum,
            'debts_dollar' => $debts_dollar
        ]);
    }

    public function showCompanyData($company)
    {
        // $data = $company->companyLog()->with(['branch', 'type', 'company'])
        //     ->where('branch_id', auth()->user()->branch_id)
        //     ->get();

        // $debts_dollar = 0;
        // $payments_dollar = 0;
        // foreach ($company->companyLog as $val) {
        //     if ($val->type_id == 4 && $val->price_id == 1) {
        //         $dollar = CurrencyRate::where('created_at', '<=', $val->created_at)->where('updated_at', '>', $val->created_at)->value('price');
        //         if (!$dollar) {
        //             $dollar = CurrencyRate::orderBy('id', 'desc')->value('price');
        //         }
        //         $debts_dollar = $debts_dollar + $val->price / $dollar;
        //     } else if ($val->type_id == 4 && $val->price_id == 2) {
        //         $debts_dollar = $debts_dollar + $val->price;
        //     } else if ($val->type_id != 4 && $val->price_id == 1) {
        //         $dollar = CurrencyRate::where('created_at', '<=', $val->created_at)->where('updated_at', '>', $val->created_at)->value('price');
        //         if (!$dollar) {
        //             $dollar = CurrencyRate::orderBy('id', 'desc')->value('price');
        //         }
        //         $payments_dollar = $payments_dollar + $val->price / $dollar;
        //     } else if ($val->type_id != 4 && $val->price_id == 2) {
        //         $payments_dollar = $payments_dollar + $val->price;
        //     }
        // }
        // $dollar = Price::where('id', 2)->value('value');
        // $total_sum = 0;
        // $total_dollar = $debts_dollar - $payments_dollar;
        // $total_sum = $total_dollar * $dollar;
        $dollar = Price::where('id', 2)->value('value');

        $data = $company->companyLog()->with(['branch', 'type', 'company'])->where('branch_id', auth()->user()->branch_id)->get();
        // Calculate total debts and payments in both currencies
        $debts_sum = $company->companyLog()->where('type_id', 4)->where('price_id', 1)->sum('price');
        $debts_dollar = $company->companyLog()->where('type_id', 4)->where('price_id', 2)->sum('price');
        $payments_dollar = $company->companyLog()->where('type_id', '!=', 4)->where('price_id', 2)->sum('price');
        $payments_sum = $company->companyLog()->where('type_id', '!=', 4)->where('price_id', 1)->sum('price');

        // Calculate total debts and payments in soums and dollars
        $total_sum = $debts_sum - $payments_sum;
        $total_dollar = $debts_dollar - $payments_dollar;

        // // Convert negative totals to positive if necessary
        // if ($total_sum < 0) {
        //     $total_dollar -= abs($total_sum) / $dollar; // Convert soums to dollars
        //     // return response()->json($total_dollar);
        //     $total_sum = 0;
        // } else if ($total_dollar < 0) {
        //     $total_sum -= abs($total_dollar) * $dollar; // Convert dollars to soums
        //     $total_dollar = 0;
        // }
        return [$data, $total_dollar, $total_sum];
    }

    public function baskets(Company $company)
    {
        $orders = $company->orders()->where('status', 0)->get();
        return response()->json($orders);
    }
    public function storeToCompany(AttachStoresRequest $request, Company $company)
    {
        foreach ($request->stores as $store) {
            $store = Store::where('id', $store['store_id'])->first();
            if (!$store) {
                return response()->json([
                    'error' => 'Store not found'
                ]);
            }
            $store->update(['company_id' => $company->id]);
        }
        return response()->json([
            'success' => true
        ]);
    }

    public function addStore(AddStoreRequest $request, Company $company)
    {
        // Check if the store exists
        $store = Store::findOrFail($request->store_id);
        $price = $store->price_come;
        $qty = $request->qty;

        // Increment store quantity
        $store->increment('quantity', $qty);

        // Create company log
        $company->companyLog()->create([
            'type_id' => 4,
            'price_id' => $store->price_id,
            'comment' => $request->comment,
            'price' => $price * $qty,
            'branch_id' => $company->branch_id,
        ]);

        // Get updated company data
        list($data, $debts_dollar, $debts_sum) = $this->showCompanyData($company);

        return response()->json([
            'data' => $data,
            'debts_sum' => $debts_sum,
            'debts_dollar' => $debts_dollar
        ]);
    }


    public function stores(Company $company)
    {
        $query = Store::query()
            ->with(['media', 'category', 'branch', 'price'])
            ->where('company_id', $company->id)
            ->where('status', 1);

        // Paginate the results
        $stores = $query->orderBy("quantity", 'ASC')->paginate(10);

        return response()->json($stores);
    }
}
