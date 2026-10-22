<?php

use App\Http\Controllers\Api\AtomMasterDataController;
use App\Http\Controllers\Api\FitAndGoApiController;
use App\Http\Controllers\Api\FitPhotoSessionController;
use App\Http\Controllers\Api\LedAmbienceApiController;
use App\Http\Controllers\Api\PimMediaController;
use App\Http\Controllers\Api\PimProductController;
use App\Http\Controllers\Api\PrintRuleController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RfidTagController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TableExpeditionApiController;
use App\Http\Controllers\Api\TabletDisplayController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Middleware\AuthenticateFitPhotoDevice;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EIGER Backend - API Routes
|--------------------------------------------------------------------------
|
| Endpoint inbound PIM memakai Bearer token sementara. Endpoint perangkat
| memakai token pairing masing-masing; endpoint katalog tetap read-only.
|
*/

// Products
Route::get('pim-media/{path}', [PimMediaController::class, 'show'])->where('path', '.*');
Route::post('integrations/pim/product', [PimProductController::class, 'store']);
Route::post('integrations/pim/image', [PimProductController::class, 'storeImage']);
// Product master data is created and refreshed only through the PIM integration.
// The generic catalog API is read-only; CMS cleanup remains behind the admin route.
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::prefix('master-data')->group(function () {
    Route::get('categories', [AtomMasterDataController::class, 'categories']);
    Route::get('activities', [AtomMasterDataController::class, 'activities']);
});

// Interactive tablet devices
Route::post('tablets/activate', [TabletDisplayController::class, 'activate']);
Route::get('tablets/{tablet:slug}/display', [TabletDisplayController::class, 'show']);
Route::post('tablets/{tablet:slug}/heartbeat', [TabletDisplayController::class, 'heartbeat']);

// Zones
Route::apiResource('zones', ZoneController::class)->only(['index', 'show']);
Route::middleware(['web', 'cms-api:zones.edit'])->group(function () {
    Route::post('zones', [ZoneController::class, 'store']);
    Route::match(['put', 'patch'], 'zones/{zone}', [ZoneController::class, 'update']);
});
Route::delete('zones/{zone}', [ZoneController::class, 'destroy'])->middleware(['web', 'cms-api:zones.delete']);

// RFID Tags
Route::post('rfid-tags/resolve', [RfidTagController::class, 'resolve']);
// External scanners register unassigned UIDs without a CMS session or CSRF token.
// Product mappings and channel settings remain protected management operations.
Route::post('rfid-tags/batch', [RfidTagController::class, 'batchStore']);
Route::apiResource('rfid-tags', RfidTagController::class)->only(['index', 'show']);
Route::middleware(['web', 'cms-api:rfid-tags.edit'])->group(function () {
    Route::post('rfid-tags', [RfidTagController::class, 'store']);
    Route::match(['put', 'patch'], 'rfid-tags/{rfid_tag}', [RfidTagController::class, 'update']);
});
Route::delete('rfid-tags/{rfid_tag}', [RfidTagController::class, 'destroy'])->middleware(['web', 'cms-api:rfid-tags.delete']);

// Print Rules (index + update only — no create/delete)
Route::get('print-rules', [PrintRuleController::class, 'index']);
Route::put('print-rules/{printRule}', [PrintRuleController::class, 'update'])->middleware(['web', 'cms-api:print-history.edit']);

// Sync Endpoints (placeholders for future integrations)
Route::prefix('sync')->group(function () {
    Route::post('care', [SyncController::class, 'care'])->middleware(['web', 'cms-api:integrations.edit']);
});

// AI Fit & Go Kiosk & GPU Workstation APIs (v1)
Route::prefix('v1/fit-and-go')->group(function () {
    Route::prefix('kiosks/{deviceCode}/sessions')->middleware(AuthenticateFitPhotoDevice::class)->group(function () {
        Route::get('/capabilities', [FitPhotoSessionController::class, 'capabilities']);
        Route::post('/', [FitPhotoSessionController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/{code}', [FitPhotoSessionController::class, 'show'])->where('code', '[A-F0-9]{10}');
        Route::get('/{code}/image', [FitPhotoSessionController::class, 'image'])->where('code', '[A-F0-9]{10}');
    });
    Route::post('activate', [FitAndGoApiController::class, 'activate']);
    Route::get('kiosks/{deviceCode}', [FitAndGoApiController::class, 'kiosk']);
    Route::get('config', [FitAndGoApiController::class, 'config']);
    Route::get('activities', [FitAndGoApiController::class, 'activities']);
    Route::get('activities/{activity:slug}/recommendations', [FitAndGoApiController::class, 'recommendations']);
    Route::get('categories', [FitAndGoApiController::class, 'categories']);
    Route::get('products', [FitAndGoApiController::class, 'products']);
    Route::get('products/{product}', [FitAndGoApiController::class, 'showProduct']);
    Route::get('search', [FitAndGoApiController::class, 'search']);
    Route::post('heartbeat', [FitAndGoApiController::class, 'heartbeat']);
});

// LED Ambience (Immersive Ambience Digital) APIs (v1)
Route::prefix('v1/led-ambience')->group(function () {
    Route::get('idle', [LedAmbienceApiController::class, 'idle']);
    Route::get('scenes', [LedAmbienceApiController::class, 'scenes']);
    Route::post('trigger', [LedAmbienceApiController::class, 'trigger']);
    Route::post('item-lost', [LedAmbienceApiController::class, 'itemLost']);
    Route::get('status', [LedAmbienceApiController::class, 'status']);
});

// Table Expedition (Table Expedition Hub / Product Knowledge) APIs (v1)
Route::prefix('v1/table-expedition')->group(function () {
    Route::get('standby', [TableExpeditionApiController::class, 'standby']);
    Route::post('scan', [TableExpeditionApiController::class, 'scan']);
    Route::post('item-lost', [TableExpeditionApiController::class, 'itemLost']);
    Route::post('compare', [TableExpeditionApiController::class, 'compare']);
    Route::get('status', [TableExpeditionApiController::class, 'status']);
});
