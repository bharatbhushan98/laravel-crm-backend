<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RoleController;
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
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth routes
Route::prefix('auth')->group(function () {
    // Registration removed, only login
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/theme', [AuthController::class, 'getTheme']);
        Route::post('/theme', [AuthController::class, 'updateTheme']);
    });
});

// Employee routes (Admin/Sales panel se employee create & list)
Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::put('/{id}', [EmployeeController::class, 'update']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
});
       
// GET: Fetch all roles and permissions
Route::get('/roles-permissions', [RoleController::class, 'index']);

// POST: Create new role
Route::post('/roles', [RoleController::class, 'store']);

// PUT: Update role and permissions
Route::put('/roles/{roleId}', [RoleController::class, 'update']);

// DELETE: Delete a role
Route::delete('/roles/{roleId}', [RoleController::class, 'destroy']);

// POST: Assign role to user
Route::post('/users/{user}/assign-role', [RoleController::class, 'assignRoleToUser']);

    
Route::get('/reports/finance', function () {
    // Yeh route tabhi access hoga jab user ke paas 'view-finance-reports' permission hogi
    return response()->json(['data' => 'This is sensitive finance data']);
})->middleware('permission:view-finance-reports');

// Type routes
Route::get('/types', [TypeController::class, 'index']);

Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::get('/{id}', [CustomerController::class, 'show']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::put('/{id}', [CustomerController::class, 'update']);
    Route::delete('/{id}', [CustomerController::class, 'destroy']);
});

Route::prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{id}', [OrderController::class, 'show']);
    Route::get('/grouped/all', [OrderController::class, 'getOrdersGrouped']);
    Route::delete('/{id}', [OrderController::class, 'destroy']);
});

// company-profile
Route::get('/company-profile', [CompanyProfileController::class, 'index']);
Route::post('/company-profile', [CompanyProfileController::class, 'store']);
Route::put('/company-profile/{id}', [CompanyProfileController::class, 'update']);
Route::delete('/company-profile/{id}', [CompanyProfileController::class, 'destroy']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);

Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

Route::post('/products/{productId}/suppliers', [ProductSupplierController::class, 'store']);
Route::get('/products/{productId}/suppliers', [ProductSupplierController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/all', [ProductController::class, 'allProducts']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);
Route::post('/products/{id}/set-price', [ProductController::class, 'setPrice']);
Route::put('/products/{id}/update-price', [ProductController::class, 'updatePrice']);

Route::get('/batches', [BatchController::class, 'index']);
Route::post('/batches', [BatchController::class, 'store']);

// Invoice CRUD
Route::apiResource('invoices', InvoiceController::class)->only(['index','show','store']);

// Mark invoice paid
Route::post('invoices/{id}/mark-paid', [InvoiceController::class, 'markPaid']);

// Exports
Route::get('invoices/{id}/export-pdf', [InvoiceController::class, 'exportPDF']);
Route::get('invoices/export-excel', [InvoiceController::class, 'exportExcel']);

Route::post('/invoices/{id}/send-email', [InvoiceController::class, 'sendInvoiceEmail']);

//Purchase 
Route::get('/purchases', [PurchaseController::class, 'index']);
Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
Route::post('/purchases', [PurchaseController::class, 'store']);
Route::put('/purchases/{id}', [PurchaseController::class, 'update']);
Route::delete('/purchases/{id}', [PurchaseController::class, 'destroy']);

//low-stock-items
Route::get('/low-stock-items', [LowStockItemController::class, 'index']);
Route::post('/low-stock-items', [LowStockItemController::class, 'store']);
Route::post('/low-stock-items/auto-generate', [LowStockItemController::class, 'autoGenerateLowStock']);
Route::post('/low-stock-items/send', [LowStockItemController::class, 'sendLowStockItem']);

//Purchase-orders
Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);

// Get the current discount rule (for frontend load)
Route::get('/discount-rule', [DiscountRuleController::class, 'getRule']);

// Save or update the discount rule
Route::post('/discount-rule', [DiscountRuleController::class, 'saveRule']);