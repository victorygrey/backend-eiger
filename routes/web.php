<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PrintRuleController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RfidTagController;
use App\Http\Controllers\Admin\SyncLogController;
use App\Http\Controllers\Admin\ZoneController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// ====== Admin Console ======
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::get('/products',                [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create',         [ProductController::class, 'create'])->name('products.create');
    Route::post('/products',               [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}',      [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}',   [ProductController::class, 'destroy'])->name('products.destroy');

    // Zones
    Route::get('/zones',                [ZoneController::class, 'index'])->name('zones.index');
    Route::get('/zones/create',         [ZoneController::class, 'create'])->name('zones.create');
    Route::post('/zones',               [ZoneController::class, 'store'])->name('zones.store');
    Route::get('/zones/{zone}/edit',    [ZoneController::class, 'edit'])->name('zones.edit');
    Route::put('/zones/{zone}',         [ZoneController::class, 'update'])->name('zones.update');
    Route::delete('/zones/{zone}',      [ZoneController::class, 'destroy'])->name('zones.destroy');

    // RFID Tags (binding key 'rfidTag')
    Route::get('/rfid-tags',                 [RfidTagController::class, 'index'])->name('rfid-tags.index');
    Route::get('/rfid-tags/create',          [RfidTagController::class, 'create'])->name('rfid-tags.create');
    Route::post('/rfid-tags',                [RfidTagController::class, 'store'])->name('rfid-tags.store');
    Route::get('/rfid-tags/{rfidTag}/edit',  [RfidTagController::class, 'edit'])->name('rfid-tags.edit');
    Route::put('/rfid-tags/{rfidTag}',       [RfidTagController::class, 'update'])->name('rfid-tags.update');
    Route::delete('/rfid-tags/{rfidTag}',    [RfidTagController::class, 'destroy'])->name('rfid-tags.destroy');

    // Print Rules
    Route::get('/print-rules',                [PrintRuleController::class, 'index'])->name('print-rules.index');
    Route::get('/print-rules/{printRule}/edit',[PrintRuleController::class, 'edit'])->name('print-rules.edit');
    Route::put('/print-rules/{printRule}',    [PrintRuleController::class, 'update'])->name('print-rules.update');

    // Sync Logs (read-only)
    Route::get('/sync-logs',                 [SyncLogController::class, 'index'])->name('sync-logs.index');
    Route::get('/sync-logs/{syncLog}',       [SyncLogController::class, 'show'])->name('sync-logs.show');
});
