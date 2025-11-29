<?php

use Illuminate\Support\Facades\Broadcast;   // MUST IMPORT
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\LowStockItemController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ProductSupplierController;
use App\Http\Controllers\DiscountRuleController;

/*
|--------------------------------------------------------------------------
| BROADCASTING AUTH ROUTE – THIS FIXES 403 FOREVER
|--------------------------------------------------------------------------
|
| USE 'web' MIDDLEWARE – NOT 'auth:sanctum' 
| Because we want Sanctum to authenticate via Cookie (stateful), not Bearer token
|
*/

    Route::post('/broadcasting/auth', function () {
        return auth()->user();
    })->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/theme', [AuthController::class, 'getTheme']);
        Route::post('/theme', [AuthController::class, 'updateTheme']);
    });
});

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (No Auth)
|--------------------------------------------------------------------------
*/
Route::get('/types', [TypeController::class, 'index']);

/*
|--------------------------------------------------------------------------
| EMPLOYEES & ROLES (Some public, some protected)
|--------------------------------------------------------------------------
*/
Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::put('/{id}', [EmployeeController::class, 'update']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
});

Route::get('/roles-permissions', [RoleController::class, 'index']);
Route::post('/roles', [RoleController::class, 'store']);
Route::put('/roles/{roleId}', [RoleController::class, 'update']);
Route::delete('/roles/{roleId}', [RoleController::class, 'destroy']);
Route::post('/users/{user}/assign-role', [RoleController::class, 'assignRoleToUser']);

/*
|--------------------------------------------------------------------------
| ALL PROTECTED ROUTES (Require Login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/mark-read/{id}', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // CUSTOMERS
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
    });

    // ORDERS
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/grouped/all', [OrderController::class, 'getOrdersGrouped']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/', [OrderController::class, 'store']);
        Route::delete('/{id}', [OrderController::class, 'destroy']);
    });

    // BATCHES
    Route::get('/batches', [BatchController::class, 'index']);
    Route::post('/batches', [BatchController::class, 'store']);
    Route::put('/batches/{id}', [BatchController::class, 'update']);
    Route::delete('/batches/{id}', [BatchController::class, 'destroy']);

    // COMPANY PROFILE
    Route::get('/company-profile', [CompanyProfileController::class, 'index']);
    Route::post('/company-profile', [CompanyProfileController::class, 'store']);
    Route::put('/company-profile/{id}', [CompanyProfileController::class, 'update']);
    Route::delete('/company-profile/{id}', [CompanyProfileController::class, 'destroy']);

    // CATEGORIES
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // SUPPLIERS
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

    // PRODUCTS
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/all', [ProductController::class, 'allProducts']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/set-price', [ProductController::class, 'setPrice']);
    Route::put('/products/{id}/update-price', [ProductController::class, 'updatePrice']);

    // PRODUCT - SUPPLIER
    Route::post('/products/{productId}/suppliers', [ProductSupplierController::class, 'store']);
    Route::get('/products/{productId}/suppliers', [ProductSupplierController::class, 'show']);

    // PURCHASES
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
    Route::post('/purchases', [PurchaseController::class, 'store']);
    Route::put('/purchases/{id}', [PurchaseController::class, 'update']);
    Route::delete('/purchases/{id}', [PurchaseController::class, 'destroy']);

    // INVOICES
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'store']);
    Route::post('invoices/{id}/mark-paid', [InvoiceController::class, 'markPaid']);
    Route::get('invoices/{id}/export-pdf', [InvoiceController::class, 'exportPDF']);
    Route::get('invoices/export-excel', [InvoiceController::class, 'exportExcel']);
    Route::post('/invoices/{id}/send-email', [InvoiceController::class, 'sendInvoiceEmail']);

    // LOW STOCK
    Route::get('/low-stock-items', [LowStockItemController::class, 'index']);
    Route::post('/low-stock-items', [LowStockItemController::class, 'store']);
    Route::post('/low-stock-items/auto-generate', [LowStockItemController::class, 'autoGenerateLowStock']);
    Route::post('/low-stock-items/send', [LowStockItemController::class, 'sendLowStockItem']);

    // PURCHASE ORDERS
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
    Route::delete('/purchase-orders/{id}', [PurchaseOrderController::class, 'destroy']);

    // DISCOUNT RULE
    Route::get('/discount-rule', [DiscountRuleController::class, 'getRule']);
    Route::post('/discount-rule', [DiscountRuleController::class, 'saveRule']);
});