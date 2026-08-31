<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DashboardController;
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

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStockProducts']);
    Route::get('/dashboard/expiring-soon', [DashboardController::class, 'expiringProducts']);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('products/search', [ProductController::class, 'search']);
    Route::get('products/{product}/stock', [ProductController::class, 'stock']);

    // Warehouses
    Route::apiResource('warehouses', WarehouseController::class);
    Route::get('warehouses/{warehouse}/products', [WarehouseController::class, 'products']);

    // Stock Movements
    Route::apiResource('stock-movements', StockMovementController::class);
    Route::post('products/{product}/entry', [StockMovementController::class, 'entry']);
    Route::post('products/{product}/exit', [StockMovementController::class, 'exit']);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Orders - Admin/Seller only
    Route::middleware('role:seller,admin')->group(function () {
        Route::apiResource('orders', OrderController::class);
        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice']);
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm']);
        Route::post('orders/{order}/complete', [OrderController::class, 'complete']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{order}/payments', [OrderController::class, 'addPayment']);
    });
});
