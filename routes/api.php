<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\BasketController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ReturnedStoreController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    $token = $user->createToken('auth_token', ['expires' => now()->addDay()])->plainTextToken;

    return response()->json([
        'user' => $user->load('UserAccess.Access:id,name'),
        'token' => $token,
    ]);
});
Route::post('/login', [UserController::class, 'login']);


Route::middleware(['auth:sanctum'])->group(function () {
    // Users routes
    Route::post('/user', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/user/{user}', [UserController::class, 'update']);
    Route::post('/user/{user}/payment', [UserController::class, 'payment']);
    Route::get('/user/{user}/history', [UserController::class, 'paymentHistory']);
    Route::post('/user/{user}/payment/update', [UserController::class, 'updatePayment']);
    Route::delete('/user/{user}/payment/{payment}', [UserController::class, 'deletePayment']);
    Route::delete('/user/{user}', [UserController::class, 'destroy']);



    // Access routes
    Route::get('/access', [AccessController::class, 'index']);
    Route::post('/access', [AccessController::class, 'store']);

    // Branch routes
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branch', [BranchController::class, 'store']);
    Route::post('/transfer', [BranchController::class, 'transfer']);

    // Category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/category', [CategoryController::class, 'store']);
    Route::delete('/category/{category}', [CategoryController::class, 'destroy']);

    // Company routes
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/company/{company}', [CompanyController::class, 'show']);
    Route::post('/company/update/{company}', [CompanyController::class, 'update']);
    Route::post('/company/pay/{company}', [CompanyController::class, 'pay']);
    Route::post('/company/debt/{company}', [CompanyController::class, 'debt']);
    Route::post('/company/debt/{company}/update', [CompanyController::class, 'updateDebt']);
    Route::post('/company/debt/{company}/delete', [CompanyController::class, 'deleteDebt']);
    Route::post('/company/{company}/stores/attach', [CompanyController::class, 'storeToCompany']);
    Route::post('/company/{company}/stores/add', [CompanyController::class, 'addStore']);
    Route::post('/company', [CompanyController::class, 'store']);
    Route::delete('/company/{company}', [CompanyController::class, 'destroy']);

    // Type routes
    Route::get('/types', [TypeController::class, 'index']);
    Route::post('/type', [TypeController::class, 'store']);
    Route::post('/type/{type}', [TypeController::class, 'update']);
    Route::delete('/type/{type}', [TypeController::class, 'destroy']);

    // Price routes
    Route::get('/prices', [PriceController::class, 'index']);
    Route::post('/price', [PriceController::class, 'store']);
    Route::post('/price/{price}', [PriceController::class, 'update']);

    // Product routes
    Route::get('/products', [StoreController::class, 'index']);
    Route::get('/products/calculate', [StoreController::class, 'calculate']);
    Route::post('/product', [StoreController::class, 'store']);
    Route::post('/product/{item}', [StoreController::class, 'update']);
    Route::delete('/products/delete', [StoreController::class, 'destroy']);

    // Customer routes
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customer/{customer}', [CustomerController::class, 'show']);
    Route::post('/customer/update/{customer}', [CustomerController::class, 'update']);
    Route::post('/customer', [CustomerController::class, 'store']);
    Route::post('/customer/{customer}', [CustomerController::class, 'pay']);
    Route::post('/customer/debt/{customer}', [CustomerController::class, 'addDebt']);
    Route::post('/customer/debt/{customer}/update', [CustomerController::class, 'updateDebt']);
    Route::post('/customer/debt/{customer}/delete', [CustomerController::class, 'deleteDebt']);
    Route::delete('/customer/{customer}', [CustomerController::class, 'destroy']);
    Route::get('/customer/{customer}/products', [CustomerController::class, 'showCustomerProduct']);

    // Basket routes
    Route::get('/baskets', [BasketController::class, 'index']);
    Route::post('/basket', [BasketController::class, 'store']);
    Route::post('/basket/save', [BasketController::class, 'save']);
    Route::post('/basket/update', [BasketController::class, 'update']);
    Route::post('/basket/delete', [BasketController::class, 'destroy']);
    Route::post('/basket/waiting', [BasketController::class, 'toWaiting']);

    // Expences routes
    Route::get('/expenses', [ExpenceController::class, 'index']);
    Route::post('/expense', [ExpenceController::class, 'store']);
    Route::post('/expense/{expence}', [ExpenceController::class, 'update']);
    Route::delete('/expenses/{expence}', [ExpenceController::class, 'destroy']);

    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/selled', [OrderController::class, 'selled']);
    Route::get('/order/{order}', [OrderController::class, 'show']);
    Route::get('/orders/waiting', [OrderController::class, 'waitingOrders']);
    Route::get('/orders/waiting/{order}', [OrderController::class, 'waitingOrder']);
    Route::get('/orders/unwaiting/{order}', [BasketController::class, 'unwaitOrder']);

    // Returned routes
    Route::get('/returned', [ReturnedStoreController::class, 'index']);
    Route::post('/return', [ReturnedStoreController::class, 'store']);

    // Statistics routes
    Route::get('/statistics', [StatisticController::class, 'index']);
    Route::get('/statistics/report', [StatisticController::class, 'calc']);
    Route::get('/statistics/branches/{start?}/{finish?}', [StatisticController::class, 'branchesStat']);
});
