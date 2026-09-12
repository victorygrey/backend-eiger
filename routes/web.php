<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RfidTagController;
use App\Http\Controllers\Admin\SyncLogController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Admin\TabletController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// ====== Admin Console ======
Route::prefix('admin')->name('admin.')->group(function () {
    // Unified PIM & CARE Integration
    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\IntegrationController::class, 'index'])->name('index');
        Route::post('/sync', [\App\Http\Controllers\Admin\IntegrationController::class, 'syncAll'])->name('sync-all');
        Route::post('/sync-pim', [\App\Http\Controllers\Admin\IntegrationController::class, 'syncPim'])->name('sync-pim');
        Route::post('/sync-care', [\App\Http\Controllers\Admin\IntegrationController::class, 'syncCare'])->name('sync-care');
        Route::post('/scan-folder', [\App\Http\Controllers\Admin\IntegrationController::class, 'scanFolder'])->name('scan-folder');
    });

    // PIM Legacy / Dedicated Endpoints
    Route::get('/pim', [\App\Http\Controllers\Admin\PimController::class, 'index'])->name('pim.index');
    Route::post('/pim/scan', [\App\Http\Controllers\Admin\PimController::class, 'scan'])->name('pim.scan');
    Route::post('/pim/sync', [\App\Http\Controllers\Admin\PimController::class, 'sync'])->name('pim.sync');
    Route::get('/pim/qa', [\App\Http\Controllers\Admin\PimController::class, 'qa'])->name('pim.qa');
    Route::match(['get', 'post'], '/pim/{endpoint}', [\App\Http\Controllers\Admin\PimController::class, 'proxy'])->name('pim.proxy');

    // CARE Legacy / Dedicated Endpoints
    Route::get('/care', [\App\Http\Controllers\Admin\CareController::class, 'index'])->name('care.index');
    Route::post('/care/sync', [\App\Http\Controllers\Admin\CareController::class, 'sync'])->name('care.sync');

    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::get('/products/pim-lookup', [ProductController::class, 'pimLookup'])->name('products.pim-lookup');
    Route::get('/products/catalog-lookup', [ProductController::class, 'catalogLookup'])->name('products.catalog-lookup');
    Route::get('/products',                [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create',         [ProductController::class, 'create'])->name('products.create');
    Route::post('/products',               [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}',      [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}',   [ProductController::class, 'destroy'])->name('products.destroy');

    // Interactive Tablets
    Route::get('/tablets',                  [TabletController::class, 'index'])->name('tablets.index');
    Route::get('/tablets/create',           [TabletController::class, 'create'])->name('tablets.create');
    Route::post('/tablets',                 [TabletController::class, 'store'])->name('tablets.store');
    Route::get('/tablets/{tablet}/edit',    [TabletController::class, 'edit'])->name('tablets.edit');
    Route::put('/tablets/{tablet}',         [TabletController::class, 'update'])->name('tablets.update');
    Route::post('/tablets/{tablet}/versions/{version}/rollback', [TabletController::class, 'rollback'])->name('tablets.rollback');
    Route::delete('/tablets/{tablet}',      [TabletController::class, 'destroy'])->name('tablets.destroy');

    // Zones
    Route::get('/zones',                [ZoneController::class, 'index'])->name('zones.index');
    Route::get('/zones/create',         [ZoneController::class, 'create'])->name('zones.create');
    Route::post('/zones',               [ZoneController::class, 'store'])->name('zones.store');
    Route::get('/zones/{zone}/edit',    [ZoneController::class, 'edit'])->name('zones.edit');
    Route::put('/zones/{zone}',         [ZoneController::class, 'update'])->name('zones.update');
    Route::delete('/zones/{zone}',      [ZoneController::class, 'destroy'])->name('zones.destroy');

    // RFID Tags (Add & View only - no edit, no delete)
    Route::get('/rfid-tags',                 [RfidTagController::class, 'index'])->name('rfid-tags.index');
    Route::get('/rfid-tags/create',          [RfidTagController::class, 'create'])->name('rfid-tags.create');
    Route::post('/rfid-tags',                [RfidTagController::class, 'store'])->name('rfid-tags.store');

    // Sync Logs (read-only)
    Route::get('/sync-logs',                 [SyncLogController::class, 'index'])->name('sync-logs.index');
    Route::get('/sync-logs/{syncLog}',       [SyncLogController::class, 'show'])->name('sync-logs.show');
});
