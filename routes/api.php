<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
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


    // Access routes
    Route::get('/access', [AccessController::class, 'index']);
    Route::post('/access', [AccessController::class, 'store']);

    // Branch routes
    Route::get('/branch', [BranchController::class, 'index']);
    Route::post('/branch', [BranchController::class, 'store']);

    // Category routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/category', [CategoryController::class, 'store']);

    // Company routes
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/company', [CompanyController::class,'store']);

    // Type routes
    Route::get('/types', [TypeController::class, 'index']);
    Route::post('/type', [TypeController::class,'store']);
});
