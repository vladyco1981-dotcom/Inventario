<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\OrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/recent-activity', [DashboardController::class, 'getRecentActivity']);
        Route::get('/sales-chart', [DashboardController::class, 'getSalesChart']);
        Route::get('/top-products', [DashboardController::class, 'getTopProducts']);
        Route::get('/low-stock-alerts', [DashboardController::class, 'getLowStockAlerts']);
        Route::get('/expiring-alerts', [DashboardController::class, 'getExpiringAlerts']);
    });

    // Inventory Module
    Route::prefix('inventory')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('stock-movements', StockMovementController::class);
        
        // Product specific routes
        Route::get('products/low-stock', [ProductController::class, 'lowStock']);
        Route::get('products/expiring-soon/{days?}', [ProductController::class, 'expiringSoon']);
        
        // Stock adjustment
        Route::post('stock/adjust', [StockMovementController::class, 'adjustStock']);
    });

    // Sales Module
    Route::prefix('sales')->group(function () {
        Route::apiResource('customers', CustomerController::class);
        
        // Orders - requires seller or admin role
        Route::middleware('role:seller,admin')->group(function () {
            Route::apiResource('orders', OrderController::class);
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::post('orders/{order}/payments', [OrderController::class, 'registerPayment']);
        });
    });

    // Admin Module - requires admin role
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::post('users/{user}/assign-roles', [UserController::class, 'assignRoles']);
    });
});
