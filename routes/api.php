<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index']);

// Customers
Route::get('/customers', [CustomerController::class, 'index']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::get('/customers/{customer}', [CustomerController::class, 'show']);
Route::put('/customers/{customer}', [CustomerController::class, 'update']);
Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

// Parts
Route::get('/parts', [PartController::class, 'index']);
Route::post('/parts', [PartController::class, 'store']);
Route::get('/parts/{part}', [PartController::class, 'show']);
Route::put('/parts/{part}', [PartController::class, 'update']);
Route::delete('/parts/{part}', [PartController::class, 'destroy']);

// Categories
Route::get('/categories', [PartController::class, 'categories']);
Route::post('/categories', [PartController::class, 'storeCategory']);
Route::put('/categories/{category}', [PartController::class, 'updateCategory']);
Route::delete('/categories/{category}', [PartController::class, 'destroyCategory']);

// Sales
Route::get('/sales', [SaleController::class, 'index']);
Route::post('/sales', [SaleController::class, 'store']);
Route::get('/sales/{sale}', [SaleController::class, 'show']);
Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);

// Payments
Route::get('/payments', [PaymentController::class, 'index']);
Route::post('/payments', [PaymentController::class, 'store']);
Route::get('/payments/{payment}', [PaymentController::class, 'show']);
Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);

// Suppliers
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

// Purchases
Route::get('/purchases', [PurchaseController::class, 'index']);
Route::post('/purchases', [PurchaseController::class, 'store']);
Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy']);

// Supplier Payments
Route::get('/supplier-payments', [SupplierPaymentController::class, 'index']);
Route::post('/supplier-payments', [SupplierPaymentController::class, 'store']);
Route::get('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show']);
Route::delete('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'destroy']);

// Reports
Route::get('/reports/summary', [ReportsController::class, 'summary']);
Route::get('/reports/debtors', [ReportsController::class, 'debtors']);
Route::get('/reports/creditors', [ReportsController::class, 'creditors']);
Route::get('/reports/inventory', [ReportsController::class, 'inventory']);
Route::get('/reports/sales', [ReportsController::class, 'salesReport']);
Route::get('/reports/monthly-profit', [ReportsController::class, 'monthlyProfit']);

// Backups
Route::get('/backups', [BackupController::class, 'index']);
Route::post('/backups', [BackupController::class, 'store']);
Route::get('/backups/{backup}/download', [BackupController::class, 'download']);
Route::delete('/backups/{backup}', [BackupController::class, 'destroy']);
Route::get('/backup/download-excel', [BackupController::class, 'downloadExcel']);

// Table export (in-page buttons)
Route::get('/export/table', [BackupController::class, 'exportTable']);

// Backup schedules
Route::get('/backup-schedules', [BackupController::class, 'schedules']);
Route::post('/backup-schedules', [BackupController::class, 'storeSchedule']);
Route::put('/backup-schedules/{schedule}', [BackupController::class, 'updateSchedule']);
Route::delete('/backup-schedules/{schedule}', [BackupController::class, 'destroySchedule']);
Route::get('/backup-schedules/next-run', [BackupController::class, 'nextRun']);
