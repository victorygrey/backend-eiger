<?php

use App\Http\Controllers\Admin\CareController;
use App\Http\Controllers\Admin\CmsExportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FitAndGoController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\LedAmbienceController;
use App\Http\Controllers\Admin\PimController;
use App\Http\Controllers\Admin\PrintHistoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RfidTagController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SyncLogController;
use App\Http\Controllers\Admin\TableExpeditionController;
use App\Http\Controllers\Admin\TabletController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\EnsureCmsPermission;
use App\Support\CmsPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Authentication Routes
Route::get('/cms-api/session', function (Request $request) {
    $user = $request->user();
    if ($user && (! $user->isActive() || $user->awaitingSetup())) {
        abort(403, 'Akun atau role tidak aktif.');
    }

    return response()->json([
        'api_version' => 1,
        'csrf_token' => csrf_token(),
        'user' => $user ? [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->role, 'role_name' => $user->roleName(),
            'superadmin' => $user->isSuperAdmin(),
            'permissions' => array_values(array_filter(CmsPermissions::keys(), fn ($key) => $user->hasPermission($key))),
        ] : null,
    ])->header('Cache-Control', 'private, no-store');
})->middleware('auth.session');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ====== Admin Console ======
Route::prefix('admin')->name('admin.')->middleware(['auth', 'auth.session', EnsureCmsPermission::class])->group(function () {
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

    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('role:superadmin');
    foreach (['products', 'rfid-tags', 'print-history', 'sync-logs'] as $exportModule) {
        Route::get('/'.$exportModule.'/export/csv', [CmsExportController::class, 'export'])->defaults('module', $exportModule)->name($exportModule.'.export');
    }

    // Unified PIM & CARE Integration
    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::get('/connection-status', [IntegrationController::class, 'connectionStatus'])->name('connection-status');
        Route::post('/sync', [IntegrationController::class, 'syncAll'])->name('sync-all');
        Route::post('/sync-pim', [IntegrationController::class, 'syncPim'])->name('sync-pim');
        Route::post('/sync-care', [IntegrationController::class, 'syncCare'])->name('sync-care');
        Route::post('/scan-folder', [IntegrationController::class, 'scanFolder'])->name('scan-folder');
        Route::post('/pim-token', [IntegrationController::class, 'issuePimToken'])
            ->middleware('role:superadmin')->name('pim-token.issue');
    });

    // PIM Legacy / Dedicated Endpoints
    Route::get('/pim', [PimController::class, 'index'])->name('pim.index');
    Route::post('/pim/scan', [PimController::class, 'scan'])->name('pim.scan');
    Route::post('/pim/sync', [PimController::class, 'sync'])->name('pim.sync');
    Route::get('/pim/qa', [PimController::class, 'qa'])->name('pim.qa');
    Route::match(['get', 'post'], '/pim/{endpoint}', [PimController::class, 'proxy'])->name('pim.proxy');

    // CARE Legacy / Dedicated Endpoints
    Route::get('/care', [CareController::class, 'index'])->name('care.index');
    Route::post('/care/sync', [CareController::class, 'sync'])->name('care.sync');

    Route::get('/', fn () => redirect()->route(auth()->user()->homeRoute()));

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::get('/products/pim-lookup', [ProductController::class, 'pimLookup'])->name('products.pim-lookup');
    Route::get('/products/catalog-lookup', [ProductController::class, 'catalogLookup'])->name('products.catalog-lookup');
    Route::get('/products/{product}/mapping-preview', [ProductController::class, 'mappingPreview'])->name('products.mapping-preview');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::patch('/products/channel-lock', [ProductController::class, 'updateChannelLock'])->name('products.channel-lock.update');
    Route::patch('/products/{product}/channel', [ProductController::class, 'updateChannel'])->name('products.channel.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

    // Interactive Tablets
    Route::get('/tablets', [TabletController::class, 'index'])->name('tablets.index');
    Route::get('/tablets/{tablet}/edit', [TabletController::class, 'edit'])->name('tablets.edit');
    Route::put('/tablets/{tablet}', [TabletController::class, 'update'])->name('tablets.update');
    Route::post('/tablets/{tablet}/versions/{version}/rollback', [TabletController::class, 'rollback'])->name('tablets.rollback');

    // AI Fit & Go
    Route::prefix('fit-and-go')->name('fit-and-go.')->group(function () {
        Route::get('/', [FitAndGoController::class, 'index'])->name('index');
        Route::put('/config-products/{device}', [FitAndGoController::class, 'updateProductConfig'])->name('product-config.update');
        Route::get('/products/{product}/presentation', [FitAndGoController::class, 'productPresentation'])->name('product-presentation.show');
        Route::put('/products/{product}/presentation', [FitAndGoController::class, 'updateProductPresentation'])->name('product-presentation.update');
        Route::get('/devices/{device}/edit', [FitAndGoController::class, 'editDevice'])->name('devices.edit');
        Route::put('/devices/{device}', [FitAndGoController::class, 'updateDevice'])->name('devices.update');
        Route::post('/devices/{device}/ping', [FitAndGoController::class, 'pingDevice'])->name('devices.ping');
    });

    // Photo Capture & Print History (operational log, separate from Fit & Go configuration)
    Route::get('/print-history', [PrintHistoryController::class, 'index'])->name('print-history.index');
    Route::get('/print-history/{photoCapture}/image', [PrintHistoryController::class, 'image'])->name('print-history.image');
    Route::get('/print-history/{photoCapture}', [PrintHistoryController::class, 'show'])->name('print-history.show');

    // LED Ambience (Immersive Ambience Digital)
    Route::prefix('led-ambience')->name('led-ambience.')->group(function () {
        Route::get('/', [LedAmbienceController::class, 'index'])->name('index');
        Route::get('/templates/{template}/edit', [LedAmbienceController::class, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{template}', [LedAmbienceController::class, 'updateTemplate'])->name('templates.update');
    });

    // Table Expedition (Table Expedition Hub / Product Knowledge)
    Route::prefix('table-expedition')->name('table-expedition.')->group(function () {
        Route::get('/', [TableExpeditionController::class, 'index'])->name('index');
        Route::post('/config', [TableExpeditionController::class, 'updateConfig'])->name('config');
        Route::put('/items/{item}/comparisons', [TableExpeditionController::class, 'updateComparisons'])->name('comparisons.update');
    });

    // Zones
    Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');
    Route::get('/zones/create', [ZoneController::class, 'create'])->name('zones.create');
    Route::post('/zones', [ZoneController::class, 'store'])->name('zones.store');
    Route::get('/zones/{zone}/edit', [ZoneController::class, 'edit'])->name('zones.edit');
    Route::put('/zones/{zone}', [ZoneController::class, 'update'])->name('zones.update');
    Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');

    // RFID Tags
    Route::get('/rfid-tags', [RfidTagController::class, 'index'])->name('rfid-tags.index');
    Route::patch('/rfid-tags/channel-lock', [RfidTagController::class, 'updateChannelLock'])->name('rfid-tags.channel-lock.update');
    Route::patch('/rfid-tags/{rfidTag}/channel', [RfidTagController::class, 'updateChannel'])->name('rfid-tags.channel.update');
    Route::get('/rfid-tags/create', [RfidTagController::class, 'create'])->name('rfid-tags.create');
    Route::post('/rfid-tags', [RfidTagController::class, 'store'])->name('rfid-tags.store');
    Route::get('/rfid-tags/{rfidTag}/edit', [RfidTagController::class, 'edit'])->name('rfid-tags.edit');
    Route::put('/rfid-tags/{rfidTag}', [RfidTagController::class, 'update'])->name('rfid-tags.update');
    Route::delete('/rfid-tags/{rfidTag}', [RfidTagController::class, 'destroy'])->name('rfid-tags.destroy');

    // Sync Logs (read-only)
    Route::get('/sync-logs', [SyncLogController::class, 'index'])->name('sync-logs.index');
    Route::get('/sync-logs/{syncLog}', [SyncLogController::class, 'show'])->name('sync-logs.show');
});
