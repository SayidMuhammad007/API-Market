<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddDebtRequest;
use App\Http\Requests\PayCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Basket;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\CustomerLog;
use App\Models\Price;
use App\Models\Type;
use Carbon\Carbon;
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
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('name', 'like', "%$searchTerm%");
        } elseif ($request->has('id')) {
            $searchTerm = $request->input('id');
            $query->where('id', $searchTerm);
        }

        // Paginate the results
        $customers = $query->paginate(1000);

        // Calculate debtor status for each customer
        $customers->getCollection()->transform(function ($customer) {
            $customer->debtor_status = $this->calculateDebt($customer);
            return $customer;
        });

        return response()->json($customers);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        // $branch = Branch::where('id', $request->branch_id)->first();
        // if (!$branch) {
        //     return response()->json([
        //         'message' => 'Branch not found',
        //     ], 404);
        // }
        $data = Customer::create($request->all());
        $data->update(['branch_id' => auth()->user()->branch_id]);
        return response()->json([
            'success' => true,
            'message' => 'Mijoz muvaffaqiyatli saqlandi!'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        if (request()->input('way') == 1) {
            list($data, $dollar, $sum) = $this->calculate($customer);
        } else {
            list($data, $dollar, $sum) = $this->showCompanyDataBySecondWay($customer);
        }
        return response()->json([
            'data' => $data,
            'debts' => [
                'all_dollar' => $dollar,
                'all_sum' => $sum,
            ],
        ]);
    }

    public function baskets(Customer $customer)
    {
        $orders = $customer->orders()->where('status', 0)->get();
        return response()->json($orders);
    }

    public function calculate($customer)
    {

        // Retrieve customer log data with relationships
        $data = $customer->customerLog()->with(['branch', 'type', 'customer'])
            ->where('branch_id', auth()->user()->branch_id)
            ->get();

        $debts_dollar = 0;
        $payments_dollar = 0;
        foreach ($customer->customerLog as $val) {
            if ($val->type_id == 4 && $val->price_id == 1) {
                $dollar = CurrencyRate::where('created_at', '<=', $val->created_at)->where('updated_at', '>', $val->created_at)->value('price');
                if (!$dollar) {
                    $dollar = CurrencyRate::orderBy('id', 'desc')->value('price');
                }
                $debts_dollar = $debts_dollar + $val->price / $dollar;
            } else if ($val->type_id == 4 && $val->price_id == 2) {
                $debts_dollar = $debts_dollar + $val->price;
            } else if ($val->type_id != 4 && $val->price_id == 1) {
                $dollar = CurrencyRate::where('created_at', '<=', $val->created_at)->where('updated_at', '>', $val->created_at)->value('price');
                if (!$dollar) {
                    $dollar = CurrencyRate::orderBy('id', 'desc')->value('price');
                }
                $payments_dollar = $payments_dollar + $val->price / $dollar;
            } else if ($val->type_id != 4 && $val->price_id == 2) {
                $payments_dollar = $payments_dollar + $val->price;
            }
        }
        $dollar = Price::where('id', 2)->value('value');
        $total_sum = 0;
        $total_dollar = $debts_dollar - $payments_dollar;
        $total_sum = $total_dollar * $dollar;

        // // return [$data, $total_dollar, $total_sum];
        // $dollar = Price::where('id', 2)->value('value');

        // $data = $customer->customerLog()->with(['branch', 'type', 'customer'])->where('branch_id', auth()->user()->branch_id)->get();
        // // Calculate total debts and payments in both currencies
        // $debts_sum = $customer->customerLog()->where('type_id', 4)->where('price_id', 1)->sum('price');
        // $debts_dollar = $customer->customerLog()->where('type_id', 4)->where('price_id', 2)->sum('price');
        // $payments_dollar = $customer->customerLog()->where('type_id', '!=', 4)->where('price_id', 2)->sum('price');
        // $payments_sum = $customer->customerLog()->where('type_id', '!=', 4)->where('price_id', 1)->sum('price');

        // // Calculate total debts and payments in soums and dollars
        // $total_sum = $debts_sum - $payments_sum;
        // $total_dollar = $debts_dollar - $payments_dollar;

        // // Convert negative totals to positive if necessary
        // if ($total_sum < 0) {
        //     $total_dollar -= abs($total_sum) / $dollar; // Convert soums to dollars
        //     // return response()->json($total_dollar);
        //     $total_sum = 0;
        // } else if ($total_dollar < 0) {
        //     $total_sum -= abs($total_dollar) * $dollar; // Convert dollars to soums
        //     $total_dollar = 0;
        // }
        // $all_dollar = $total_dollar + ($total_sum / $dollar);
        // $all_sum = $total_sum + ($total_dollar * $dollar);
        return [$data, $total_dollar, $total_sum];
    }

    public function showCompanyDataBySecondWay($customer)
    {
        $debts_dollar = 0;
        $payments_dollar = 0;
        $debts_dollar = $customer->customerLog->where('type_id', 4)->where('price_id', 2)->sum('price');
        $debts_soum = $customer->customerLog->where('type_id', 4)->where('price_id', 1)->sum('price');

        $payments_dollar = $customer->customerLog
            ->where('type_id', '!=', 4)
            ->where('price_id', 2)
            ->filter(function ($log) {
                return $log->parent && $log->parent->price_id == 2;
            })
            ->sum('price');
        $payments_dollar2 = $customer->customerLog
            ->where('type_id', '!=', 4)
            ->where('price_id', 1)
            ->whereNotNull('parent_id')
            ->filter(function ($log) {
                return $log->parent && $log->parent->price_id == 2;
            })
            ->sum('convert');

        $payments_soum = $customer->customerLog
            ->where('type_id', '!=', 4)
            ->where('price_id', 1)
            ->filter(function ($log) {
                return $log->parent && $log->parent->price_id == 1;
            })
            ->sum('price');

        $payments_soum2 = $customer->customerLog
            ->where('type_id', '!=', 4)
            ->where('price_id', 2)
            ->filter(function ($log) {
                return $log->parent && $log->parent->price_id == 1;
            })
            ->sum('convert');

        $customer->customerLog->where('type_id', '!=', 4)->where('price_id', 2)->whereNotNull('parent_id')->sum('convert');

        $total_dollar = $debts_dollar - $payments_dollar - $payments_dollar2;
        $total_sum = $debts_soum - $payments_soum - $payments_soum2;

        $data = $customer->customerLog()->with(['branch', 'type', 'customer'])->where('branch_id', auth()->user()->branch_id)->get();

        return [$data, $total_dollar, $total_sum];
    }

    public function calculateDebt($customer)
    {
        $debts_dollar = 0;
        $payments_dollar = 0;
        foreach ($customer->customerLog as $val) {
            if ($val->type_id == 4 && $val->price_id == 1) {
                $dollar = CurrencyRate::where('created_at', '<=', $val->created_at)->where('updated_at', '>', $val->created_at)->value('price');
                if (!$dollar) {
                    $dollar = CurrencyRate::orderBy('id', 'desc')->value('price');
                }
                $debts_dollar = $debts_dollar + $val->price / $dollar;
            } else if ($val->type_id == 4 && $val->price_id == 2) {
                $debts_dollar = $debts_dollar + $val->price;
            } else if ($val->type_id != 4 && $val->price_id == 1) {
                $dollar = CurrencyRate::where('created_at', '<=', $val->created_at)->where('updated_at', '>', $val->created_at)->value('price');
                if (!$dollar) {
                    $dollar = CurrencyRate::orderBy('id', 'desc')->value('price');
                }
                $payments_dollar = $payments_dollar + $val->price / $dollar;
            } else if ($val->type_id != 4 && $val->price_id == 2) {
                $payments_dollar = $payments_dollar + $val->price;
            }
        }
        $dollar = Price::where('id', 2)->value('value');
        $total_sum = 0;
        $total_dollar = $debts_dollar - $payments_dollar;
        $total_sum = $total_dollar * $dollar;
        $status = 'white';
        if ($total_sum > 0 || $total_dollar > 0) {
            $status = 'green';
        } elseif ($total_sum < 0 || $total_dollar < 0) {
            $status = 'red';
        }
        return $status;
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $customer->update($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Mijoz muvaffaqiyatli o`zgartirildi!'
        ]);
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
            $parent = CustomerLog::where('id', $payment['parent_id'])->first();
            $dollar = Price::where('id', 2)->value('value');
            if (!$parent) {
                return response()->json([
                    'error' => 'Parent not found'
                ]);
            }
            $convert = null;

            // Determine the conversion based on price IDs
            if ($payment['price_id'] == 1 && $parent->price_id == 2) {
                $convert = $payment['price'] / $dollar;
            } elseif ($payment['price_id'] == 2 && $parent->price_id == 1) {
                $convert = $payment['price'] * $dollar;
            }
            $customer->customerLog()->create([
                'type_id' => $payment['type_id'],
                'price_id' => $payment['price_id'],
                'comment' => $payment['comment'],
                'price' => $payment['price'],
                'convert' => $convert,
                'branch_id' => $customer->branch_id,
            ]);
        }
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
        $formattedDate = Carbon::parse($request->date)->format('Y-m-d');

        CustomerLog::create([
            'branch_id' => auth()->user()->branch_id,
            'customer_id' => $customer->id,
            'type_id' => 4,
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
            'date' => $formattedDate,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Debt added successfully'
        ]);
    }

    public function updateDebt(Customer $customer, UpdateDebtRequest $request)
    {
        CustomerLog::where('id', $request->debt_id)->update([
            'price_id' => $request->price_id,
            'comment' => $request->comment,
            'price' => $request->price,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Debt updated successfully'
        ]);
    }

    public function deleteDebt(Customer $customer, UpdateDebtRequest $request)
    {
        $findLog = CustomerLog::where('id', $request->debt_id)->first();
        if (!$findLog->exists()) {
            return response()->json([
                'error' => 'Debt not found'
            ], 404);
        }
        $findLog->delete();
        return response()->json([
            'success' => true,
            'message' => 'Debt deleted successfully'
        ]);
    }

    public function showCustomerProduct(Customer $customer)
    {
        $products = $customer->orders->load(['user', 'baskets', 'baskets.store', 'baskets.store.category', 'baskets.basket_price']);
        return response()->json($products);
    }

    public function notification($date)
    {
        $logs = CustomerLog::whereDate('date', $date)->get();
        return response()->json($logs);
    }

    public function showTodayDebts($date)
    {
        $customers = Customer::whereHas('customerLog', function ($query) use ($date) {
            $query->whereDate('date', $date);
        })->with(['customerLog' => function ($query) use ($date) {
            $query->whereDate('date', $date);
        }])->get();
        return response()->json($customers);
    }
}
