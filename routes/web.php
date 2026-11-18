<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\OpeningController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PrivateUploadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware(['auth', 'active'])->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/more', [AdminController::class, 'more'])->name('more');
    Route::get('/reports', ReportController::class)->middleware('role:ADMIN')->name('reports.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products-lookup', [ProductController::class, 'lookup'])->middleware('throttle:60,1')->name('products.lookup');
    Route::post('/products', [ProductController::class, 'store'])->middleware('role:ADMIN')->name('products.store');
    Route::get('/products/{product}/image', [ProductController::class, 'image'])->name('products.image');
    Route::get('/products/{product}/success', [ProductController::class, 'success'])->middleware('role:ADMIN')->name('products.success');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('role:ADMIN')->name('products.show');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->middleware('role:ADMIN')->name('products.update');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}/success', [SaleController::class, 'success'])->name('sales.success');

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/new', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}/success', [PurchaseController::class, 'success'])->name('purchases.success');
        Route::get('/inventory', [OperationsController::class, 'inventory'])->name('inventory.index');
        Route::post('/inventory/adjust', [OperationsController::class, 'adjust'])->name('inventory.adjust');
        Route::get('/inventory/adjustments/{movement}/success', [OperationsController::class, 'adjustmentSuccess'])->name('inventory.adjust.success');
        Route::get('/expenses/new', [OperationsController::class, 'expenseForm'])->name('expenses.create');
        Route::post('/expenses', [OperationsController::class, 'expense'])->name('expenses.store');
        Route::get('/expenses/{expense}/success', [OperationsController::class, 'expenseSuccess'])->name('expenses.success');
        Route::post('/expenses/{id}/void', [OperationsController::class, 'expenseVoid'])->name('expenses.void');

        Route::get('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings', [AdminController::class, 'settingsUpdate'])->name('admin.settings.update');
        Route::post('/admin/settings/product-categories', [AdminController::class, 'productCategoryStore'])->name('admin.settings.product-categories.store');
        Route::patch('/admin/settings/product-categories/{category}', [AdminController::class, 'productCategoryUpdate'])->name('admin.settings.product-categories.update');
        Route::post('/admin/settings/expense-categories', [AdminController::class, 'expenseCategoryStore'])->name('admin.settings.expense-categories.store');
        Route::patch('/admin/settings/expense-categories/{category}', [AdminController::class, 'expenseCategoryUpdate'])->name('admin.settings.expense-categories.update');
        Route::get('/admin/setup', [AdminController::class, 'setup'])->name('admin.setup');
        Route::get('/admin/audit', [AdminController::class, 'audit'])->name('admin.audit');
        Route::get('/admin/exceptions', [AdminController::class, 'exceptions'])->name('admin.exceptions.index');
        Route::post('/admin/exceptions/{exception}', [AdminController::class, 'reviewException'])->name('admin.exceptions.review');

        Route::get('/admin/employees', [EmployeeController::class, 'index'])->name('admin.employees.index');
        Route::post('/admin/employees', [EmployeeController::class, 'store'])->name('admin.employees.store');
        Route::patch('/admin/employees/{user}', [EmployeeController::class, 'update'])->name('admin.employees.update');
        Route::post('/admin/employees/{user}/password', [EmployeeController::class, 'password'])->name('admin.employees.password');
        Route::get('/admin/employees/{user}/activity', [EmployeeController::class, 'activity'])->name('admin.employees.activity');

        Route::get('/admin/opening-obligations', [OpeningController::class, 'obligations'])->name('admin.opening-obligations.index');
        Route::post('/admin/opening-obligations', [OpeningController::class, 'obligationCreate'])->name('admin.opening-obligations.create');
        Route::post('/admin/opening-obligations/{batch}/items', [OpeningController::class, 'obligationItem'])->name('admin.opening-obligations.items');
        Route::post('/admin/opening-obligations/{batch}/finalize', [OpeningController::class, 'obligationFinalize'])->name('admin.opening-obligations.finalize');
        Route::post('/admin/opening-inventory/preview', [OpeningController::class, 'bulkPreview'])->name('admin.opening-inventory.upload');
        Route::get('/admin/opening-inventory/preview/{import}', [OpeningController::class, 'bulkShow'])->name('admin.opening-inventory.preview');
        Route::post('/admin/opening-inventory/preview/{import}/finalize', [OpeningController::class, 'bulkFinalize'])->name('admin.opening-inventory.finalize');
        Route::get('/admin/opening-inventory/template', [OpeningController::class, 'bulkTemplate'])->name('admin.opening-inventory.template');

        Route::get('/parties', [PartyController::class, 'index'])->name('parties.index');
        Route::post('/parties/customers', [PartyController::class, 'customer'])->name('parties.customers.store');
        Route::post('/parties/suppliers', [PartyController::class, 'supplier'])->name('parties.suppliers.store');
        Route::put('/parties/suppliers/{id}', [PartyController::class, 'supplierUpdate'])->name('parties.suppliers.update');
        Route::get('/uploads', [PrivateUploadController::class, 'index'])->name('uploads.index');
        Route::post('/uploads', [PrivateUploadController::class, 'store'])->name('uploads.store');
        Route::get('/uploads/{id}', [PrivateUploadController::class, 'show'])->name('uploads.show');
    });
});
