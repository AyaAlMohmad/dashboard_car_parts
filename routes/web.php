<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/customers', function () {
    return view('customers');
});

Route::get('/parts', function () {
    return view('parts');
});

Route::get('/sales', function () {
    return view('sales');
});

Route::get('/payments', function () {
    return view('payments');
});

Route::get('/suppliers', function () {
    return view('suppliers');
});

Route::get('/purchases', function () {
    return view('purchases');
});

Route::get('/supplier-payments', function () {
    return view('supplier_payments');
});

Route::get('/reports', function () {
    return view('reports');
});

Route::get('/backups', function () {
    return view('backups');
});

Route::get('/categories', function () {
    return view('categories');
});
