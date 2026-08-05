<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CareSyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(protected CareSyncService $careSync) {}

    /**
     * Trigger a CARE synchronization.
     */
    public function care(): JsonResponse
    {
        $result = $this->careSync->sync('api');

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => [
                'source'    => $result['source'],
                'status'    => $result['status'],
                'synced_at' => $result['synced_at'],
            ],
        ], $result['success'] ? 200 : 500);
    }
}
