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
| All routes are publicly accessible during the development phase.
| Authentication (Sanctum/Token) will be added in a future phase.
|
*/

// Products
Route::get('pim-media/{filename}', [\App\Http\Controllers\Api\PimMediaController::class, 'show']);
Route::post('integrations/pim/product', [\App\Http\Controllers\Api\PimProductController::class, 'store']);
Route::apiResource('products', ProductController::class);

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
