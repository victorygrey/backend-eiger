<?php

use App\Http\Controllers\Api\PrintRuleController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RfidTagController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\TabletDisplayController;
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
Route::get('pim-media/{filename}', [\App\Http\Controllers\Api\PimMediaController::class, 'show']);
Route::post('integrations/pim/product', [\App\Http\Controllers\Api\PimProductController::class, 'store']);
Route::post('integrations/pim/image', [\App\Http\Controllers\Api\PimProductController::class, 'storeImage']);
Route::apiResource('products', ProductController::class);
Route::prefix('master-data')->group(function () {
    Route::get('categories', [\App\Http\Controllers\Api\AtomMasterDataController::class, 'categories']);
    Route::get('activities', [\App\Http\Controllers\Api\AtomMasterDataController::class, 'activities']);
});

// Interactive tablet devices
Route::post('tablets/activate', [TabletDisplayController::class, 'activate']);
Route::get('tablets/{tablet:slug}/display', [TabletDisplayController::class, 'show']);
Route::post('tablets/{tablet:slug}/heartbeat', [TabletDisplayController::class, 'heartbeat']);

// Zones
Route::apiResource('zones', ZoneController::class);

// RFID Tags
Route::post('rfid-tags/resolve', [RfidTagController::class, 'resolve']);
Route::post('rfid-tags/batch', [RfidTagController::class, 'batchStore']);
Route::apiResource('rfid-tags', RfidTagController::class);

// Print Rules (index + update only — no create/delete)
Route::get('print-rules', [PrintRuleController::class, 'index']);
Route::put('print-rules/{printRule}', [PrintRuleController::class, 'update']);

// Sync Endpoints (placeholders for future integrations)
Route::prefix('sync')->group(function () {
    Route::post('care', [SyncController::class, 'care']);
});

// AI Fit & Go Kiosk & GPU Workstation APIs (v1)
Route::prefix('v1/fit-and-go')->group(function () {
    Route::get('config', [\App\Http\Controllers\Api\FitAndGoApiController::class, 'config']);
    Route::get('activities', [\App\Http\Controllers\Api\FitAndGoApiController::class, 'activities']);
    Route::get('categories', [\App\Http\Controllers\Api\FitAndGoApiController::class, 'categories']);
    Route::get('products', [\App\Http\Controllers\Api\FitAndGoApiController::class, 'products']);
    Route::get('search', [\App\Http\Controllers\Api\FitAndGoApiController::class, 'search']);
    Route::post('heartbeat', [\App\Http\Controllers\Api\FitAndGoApiController::class, 'heartbeat']);
});

// LED Ambience (Immersive Ambience Digital) APIs (v1)
Route::prefix('v1/led-ambience')->group(function () {
    Route::get('idle', [\App\Http\Controllers\Api\LedAmbienceApiController::class, 'idle']);
    Route::get('scenes', [\App\Http\Controllers\Api\LedAmbienceApiController::class, 'scenes']);
    Route::post('trigger', [\App\Http\Controllers\Api\LedAmbienceApiController::class, 'trigger']);
    Route::post('item-lost', [\App\Http\Controllers\Api\LedAmbienceApiController::class, 'itemLost']);
    Route::get('status', [\App\Http\Controllers\Api\LedAmbienceApiController::class, 'status']);
});

// Table Expedition (Table Expedition Hub / Product Knowledge) APIs (v1)
Route::prefix('v1/table-expedition')->group(function () {
    Route::get('standby', [\App\Http\Controllers\Api\TableExpeditionApiController::class, 'standby']);
    Route::post('scan', [\App\Http\Controllers\Api\TableExpeditionApiController::class, 'scan']);
    Route::post('item-lost', [\App\Http\Controllers\Api\TableExpeditionApiController::class, 'itemLost']);
    Route::post('compare', [\App\Http\Controllers\Api\TableExpeditionApiController::class, 'compare']);
    Route::get('status', [\App\Http\Controllers\Api\TableExpeditionApiController::class, 'status']);
});
