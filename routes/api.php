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

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
    ]);
});
Route::post('/login', [UserController::class, 'login']);


Route::middleware(['auth:sanctum'])->group(function () {
    // Users routes
    Route::post('/user', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/user/{user}', [UserController::class, 'update']);



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

    // Company routes
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/company/{company}', [CompanyController::class, 'show']);
    Route::post('/company/{company}', [CompanyController::class, 'pay']);
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
    Route::post('/product', [StoreController::class, 'store']);
    Route::post('/product/{item}', [StoreController::class, 'update']);
    Route::delete('/products/delete', [StoreController::class, 'destroy']);

    // Customer routes
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customer/{customer}', [CustomerController::class, 'show']);
    Route::post('/customer', [CustomerController::class, 'store']);
    Route::delete('/customer/{customer}', [CustomerController::class, 'destroy']);
    Route::post('/customer/{customer}', [CustomerController::class, 'pay']);

    // Basket routes
    Route::get('/baskets', [BasketController::class, 'index']);
    Route::post('/basket', [BasketController::class, 'store']);
    Route::post('/basket/save', [BasketController::class, 'save']);
    Route::post('/basket/update', [BasketController::class, 'update']);
    Route::post('/basket/delete', [BasketController::class, 'destroy']);

    // Expences routes
    Route::get('/expenses', [ExpenceController::class, 'index']);
    Route::post('/expense', [ExpenceController::class, 'store']);
    Route::post('/expense/{expence}', [ExpenceController::class, 'update']);
    Route::delete('/expenses/{expence}', [ExpenceController::class, 'destroy']);

    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/order/{order}', [OrderController::class, 'show']);

    // Returned routes
    Route::get('/returned', [ReturnedStoreController::class, 'index']);
    Route::post('/return', [ReturnedStoreController::class, 'store']);
});
