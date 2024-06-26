<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\TransferBranchRequest;
use App\Models\Branch;
use App\Models\Store;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Branch::paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request)
    {
        $branch = Branch::create($request->all());
        $msg = [
            'success' => true,
            'message' => 'Branches created successfully'
        ];
        return response()->json($msg, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        //
    }

    /**
     * Transfer to another branch.
     */

    public function transfer(TransferBranchRequest $request)
    {
        $count = 0;
        foreach ($request->products as $product) {
            $branch = Branch::find($product['branch_id']);
            // check branch
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not found'
                ], 400);
            }

            $store = Store::find($product['store_id']);
            // check store
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 400);
            }

            // check quantity
            if ($store->quantity < $product['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough quantity'
                ], 400);
            }

            // check branch
            if ($branch->id == $store->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot transfer to the same branch'
                ], 400);
            }

            $store->update([
                'quantity' => $store->quantity - $product['quantity']
            ]);
            $check = Store::where('branch_id', $product['branch_id'])
                ->where('barcode', $store->barcode)
                ->where('status', 1)
                ->first();

            if ($check) {
                $count = 8;
                $check->update([
                    'quantity' => $check->quantity + $product['quantity'],
                ]);
                $check->forwardHistories()->create([
                    'user_id' => auth()->user()->id,
                    'branch_id' => auth()->user()->branch_id,
                    'count' => $product['quantity'],
                    'price_come' => $check->price_come,
                    'price_sell' => $check->price_sell,
                    'price_id' => $check->price_id,
                ]);
            } else {
                $item = Store::create([
                    'branch_id' => $branch->id,
                    'category_id' => $store->category_id,
                    'price_id' => $store->price_id,
                    'name' => $store->name,
                    'made_in' => $store->made_in,
                    'barcode' => $store->barcode,
                    'price_come' => $store->price_come,
                    'price_sell' => $store->price_sell,
                    'price_wholesale' => $store->price_wholesale,
                    'quantity' => $product['quantity'],
                    'danger_count' => $store->danger_count,
                    'status' => $store->status,
                ]);
                $item->forwardHistories()->create([
                    'user_id' => auth()->user()->id,
                    'branch_id' => auth()->user()->branch_id,
                    'count' => $product['quantity'],
                    'price_come' => $item->price_come,
                    'price_sell' => $item->price_sell,
                    'price_id' => $item->price_id,
                ]);
                $count += 1;
                if ($store->hasMedia('image')) {
                    $image = $store->getFirstMedia('image');
                    $item->addMedia($image)->toMediaCollection('images');
                }
            }
        }
        return response()->json(Store::with(['media', 'category', 'branch', 'price'])->where('branch_id', auth()->user()->branch_id)->where('status', 1)->orderBy("id", "DESC")->paginate(20));
    }
}
