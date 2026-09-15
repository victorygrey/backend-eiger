<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FitAndGoController;
use App\Http\Controllers\Admin\LedAmbienceController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\TableExpeditionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RfidTagController;
use App\Http\Controllers\Admin\SyncLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Admin\TabletController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ====== Admin Console ======
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    // User Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // User Configuration & RBAC (SuperAdmin Only)
    Route::prefix('users')->name('users.')->middleware(['role:superadmin'])->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

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
    Route::get('/products/{product}/mapping-preview', [ProductController::class, 'mappingPreview'])->name('products.mapping-preview');
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

    // AI Fit & Go
    Route::prefix('fit-and-go')->name('fit-and-go.')->group(function () {
        Route::get('/', [FitAndGoController::class, 'index'])->name('index');
        Route::get('/devices/create', [FitAndGoController::class, 'createDevice'])->name('devices.create');
        Route::post('/devices', [FitAndGoController::class, 'storeDevice'])->name('devices.store');
        Route::get('/devices/{device}/edit', [FitAndGoController::class, 'editDevice'])->name('devices.edit');
        Route::put('/devices/{device}', [FitAndGoController::class, 'updateDevice'])->name('devices.update');
        Route::put('/devices/{device}/catalog/{category}', [FitAndGoController::class, 'syncDeviceCategoryProducts'])->name('devices.catalog.sync');
        Route::put('/devices/{device}/activities/{activity}', [FitAndGoController::class, 'syncDeviceActivityProducts'])->name('devices.activities.sync');
        Route::post('/devices/{device}/ping', [FitAndGoController::class, 'pingDevice'])->name('devices.ping');
        Route::delete('/devices/{device}', [FitAndGoController::class, 'destroyDevice'])->name('devices.destroy');

        Route::get('/activities/create', [FitAndGoController::class, 'createActivity'])->name('activities.create');
        Route::post('/activities', [FitAndGoController::class, 'storeActivity'])->name('activities.store');
        Route::get('/activities/{activity}/edit', [FitAndGoController::class, 'editActivity'])->name('activities.edit');
        Route::put('/activities/{activity}', [FitAndGoController::class, 'updateActivity'])->name('activities.update');
        Route::delete('/activities/{activity}', [FitAndGoController::class, 'destroyActivity'])->name('activities.destroy');

        Route::put('/categories/{category}', [FitAndGoController::class, 'updateCategory'])->name('categories.update');
        Route::post('/items/visibility', [FitAndGoController::class, 'toggleItemVisibility'])->name('items.visibility');
    });

    // LED Ambience (Immersive Ambience Digital)
    Route::prefix('led-ambience')->name('led-ambience.')->group(function () {
        Route::get('/', [LedAmbienceController::class, 'index'])->name('index');
        Route::get('/rfid-items/create', [LedAmbienceController::class, 'createRfidItem'])->name('rfid-items.create');
        Route::post('/rfid-items', [LedAmbienceController::class, 'storeRfidItem'])->name('rfid-items.store');
        Route::get('/rfid-items/{item}/edit', [LedAmbienceController::class, 'editRfidItem'])->name('rfid-items.edit');
        Route::put('/rfid-items/{item}', [LedAmbienceController::class, 'updateRfidItem'])->name('rfid-items.update');
        Route::delete('/rfid-items/{item}', [LedAmbienceController::class, 'destroyRfidItem'])->name('rfid-items.destroy');

        Route::get('/scenes/create', [LedAmbienceController::class, 'createScene'])->name('scenes.create');
        Route::post('/scenes', [LedAmbienceController::class, 'storeScene'])->name('scenes.store');
        Route::get('/scenes/{scene}/edit', [LedAmbienceController::class, 'editScene'])->name('scenes.edit');
        Route::put('/scenes/{scene}', [LedAmbienceController::class, 'updateScene'])->name('scenes.update');
        Route::delete('/scenes/{scene}', [LedAmbienceController::class, 'destroyScene'])->name('scenes.destroy');
    });

    // Table Expedition (Table Expedition Hub / Product Knowledge)
    Route::prefix('table-expedition')->name('table-expedition.')->group(function () {
        Route::get('/', [TableExpeditionController::class, 'index'])->name('index');
        Route::get('/create', [TableExpeditionController::class, 'create'])->name('create');
        Route::post('/items', [TableExpeditionController::class, 'store'])->name('store');
        Route::get('/items/{item}/edit', [TableExpeditionController::class, 'edit'])->name('edit');
        Route::put('/items/{item}', [TableExpeditionController::class, 'update'])->name('update');
        Route::delete('/items/{item}', [TableExpeditionController::class, 'destroy'])->name('destroy');
        Route::post('/config', [TableExpeditionController::class, 'updateConfig'])->name('config');
    });

    // Zones
    Route::get('/zones',                [ZoneController::class, 'index'])->name('zones.index');
    Route::get('/zones/create',         [ZoneController::class, 'create'])->name('zones.create');
    Route::post('/zones',               [ZoneController::class, 'store'])->name('zones.store');
    Route::get('/zones/{zone}/edit',    [ZoneController::class, 'edit'])->name('zones.edit');
    Route::put('/zones/{zone}',         [ZoneController::class, 'update'])->name('zones.update');
    Route::delete('/zones/{zone}',      [ZoneController::class, 'destroy'])->name('zones.destroy');

    // RFID Tags
    Route::get('/rfid-tags',                 [RfidTagController::class, 'index'])->name('rfid-tags.index');
    Route::get('/rfid-tags/create',          [RfidTagController::class, 'create'])->name('rfid-tags.create');
    Route::post('/rfid-tags',                [RfidTagController::class, 'store'])->name('rfid-tags.store');
    Route::get('/rfid-tags/{rfidTag}/edit',  [RfidTagController::class, 'edit'])->name('rfid-tags.edit');
    Route::put('/rfid-tags/{rfidTag}',       [RfidTagController::class, 'update'])->name('rfid-tags.update');
    Route::delete('/rfid-tags/{rfidTag}',    [RfidTagController::class, 'destroy'])->name('rfid-tags.destroy');

    // Sync Logs (read-only)
    Route::get('/sync-logs',                 [SyncLogController::class, 'index'])->name('sync-logs.index');
    Route::get('/sync-logs/{syncLog}',       [SyncLogController::class, 'show'])->name('sync-logs.show');
});
