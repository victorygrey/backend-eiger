<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceScene;
use App\Models\RfidTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedAmbienceApiController extends Controller
{
    /**
     * Get the default idle ambience scene (video + audio loop).
     * SRS FR-AMB-01: Memutar idle ambience video dan audio ketika tidak ada item RFID terdeteksi.
     * GET /api/v1/led-ambience/idle
     */
    public function idle(): JsonResponse
    {
        $idleScene = LedAmbienceScene::idle()->first()
            ?? LedAmbienceScene::where('is_active', true)->orderBy('sort_order')->first();

        if (!$idleScene) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active idle ambience scene configured.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'scene_id'       => $idleScene->id,
                'name'           => $idleScene->name,
                'scene_type'     => $idleScene->scene_type,
                'video_url'      => $idleScene->video_url,
                'audio_url'      => $idleScene->audio_url,
                'lighting_color' => $idleScene->lighting_color,
                'description'    => $idleScene->description,
            ],
        ]);
    }

    /**
     * Get all active ambience scenes with audio, video, and lighting presets.
     * GET /api/v1/led-ambience/scenes
     */
    public function scenes(): JsonResponse
    {
        $scenes = LedAmbienceScene::active()->orderBy('sort_order')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $scenes->count(),
            'data'   => $scenes->map(fn ($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'scene_type'     => $s->scene_type,
                'activity_slug'  => $s->activity_slug,
                'video_url'      => $s->video_url,
                'audio_url'      => $s->audio_url,
                'lighting_color' => $s->lighting_color,
                'description'    => $s->description,
                'sort_order'     => $s->sort_order,
            ]),
        ]);
    }

    /**
     * Trigger ambience scene when RFID tag is placed on the reader.
     * SRS FR-AMB-02: Berpindah ke ambience scene yang sesuai dan memutar video serta audio.
     * POST /api/v1/led-ambience/trigger
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfid_tag' => 'required|string|max:64',
        ]);

        $rfidTag = trim(strtoupper($validated['rfid_tag']));

        // 1. Check custom LED Ambience mapping
        $item = LedAmbienceItem::with(['product.zone', 'scene'])
            ->where('rfid_tag', $rfidTag)
            ->where('is_active', true)
            ->first();

        // 2. Fallback to general rfid_tags table if not explicitly mapped
        if (!$item) {
            $generalTag = RfidTag::with('product.zone')->where('epc', $rfidTag)->first();
            if ($generalTag && $generalTag->product) {
                // Find matching scene by category or activity
                $matchedScene = LedAmbienceScene::active()->where('scene_type', 'active')->first();
                $product = $generalTag->product;

                return response()->json([
                    'status'  => 'success',
                    'matched' => true,
                    'source'  => 'general_rfid',
                    'data'    => [
                        'rfid_tag'      => $rfidTag,
                        'product'       => [
                            'id'          => $product->id,
                            'name'        => $product->name,
                            'sku'         => $product->sku,
                            'category'    => $product->category,
                            'price'       => (float) $product->price,
                            'stock'       => (int) $product->stock,
                            'image'       => $product->image,
                            'description' => $product->description,
                            'zone'        => $product->zone?->name,
                        ],
                        'activity_slug' => null,
                        'scene'         => $matchedScene ? [
                            'id'             => $matchedScene->id,
                            'name'           => $matchedScene->name,
                            'video_url'      => $matchedScene->video_url,
                            'audio_url'      => $matchedScene->audio_url,
                            'lighting_color' => $matchedScene->lighting_color,
                        ] : null,
                    ],
                ]);
            }

            return response()->json([
                'status'   => 'not_found',
                'matched'  => false,
                'message'  => "Tag RFID '{$rfidTag}' tidak terdaftar pada modul LED Ambience.",
                'rfid_tag' => $rfidTag,
            ], 404);
        }

        // Update last scanned timestamp
        $item->update(['last_scanned_at' => now()]);

        // Resolve scene
        $scene = $item->scene;
        if (!$scene && $item->activity_slug) {
            $scene = LedAmbienceScene::active()
                ->where('activity_slug', $item->activity_slug)
                ->first();
        }
        if (!$scene) {
            $scene = LedAmbienceScene::active()->where('scene_type', 'active')->first();
        }

        $product = $item->product;

        return response()->json([
            'status'  => 'success',
            'matched' => true,
            'source'  => 'led_ambience_custom',
            'data'    => [
                'rfid_tag'      => $item->rfid_tag,
                'activity_slug' => $item->activity_slug,
                'notes'         => $item->notes,
                'product'       => $product ? [
                    'id'          => $product->id,
                    'name'        => $product->name,
                    'sku'         => $product->sku,
                    'category'    => $product->category,
                    'price'       => (float) $product->price,
                    'stock'       => (int) $product->stock,
                    'image'       => $product->image,
                    'description' => $product->description,
                    'zone'        => $product->zone?->name,
                ] : null,
                'scene'         => $scene ? [
                    'id'             => $scene->id,
                    'name'           => $scene->name,
                    'scene_type'     => $scene->scene_type,
                    'video_url'      => $scene->video_url,
                    'audio_url'      => $scene->audio_url,
                    'lighting_color' => $scene->lighting_color,
                    'description'    => $scene->description,
                ] : null,
            ],
        ]);
    }

    /**
     * Notify that the item has been removed from the table / RFID reader.
     * SRS FR-AMB-03: Kembali ke Idle Screen ketika item yang terdeteksi diambil ("Item Lost").
     * POST /api/v1/led-ambience/item-lost
     */
    public function itemLost(): JsonResponse
    {
        $idleScene = LedAmbienceScene::idle()->first()
            ?? LedAmbienceScene::active()->orderBy('sort_order')->first();

        return response()->json([
            'status'  => 'success',
            'event'   => 'item_lost',
            'message' => 'Layar kembali ke mode suasana Idle (standby).',
            'data'    => [
                'scene' => $idleScene ? [
                    'id'             => $idleScene->id,
                    'name'           => $idleScene->name,
                    'scene_type'     => $idleScene->scene_type,
                    'video_url'      => $idleScene->video_url,
                    'audio_url'      => $idleScene->audio_url,
                    'lighting_color' => $idleScene->lighting_color,
                ] : null,
            ],
        ]);
    }

    /**
     * Get system status and configuration summary for LED Ambience hardware.
     * GET /api/v1/led-ambience/status
     */
    public function status(): JsonResponse
    {
        $totalItems = LedAmbienceItem::count();
        $activeItems = LedAmbienceItem::where('is_active', true)->count();
        $totalScenes = LedAmbienceScene::count();
        $hasIdle = LedAmbienceScene::idle()->exists();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_rfid_items' => $totalItems,
                'active_rfid_items'=> $activeItems,
                'total_scenes'     => $totalScenes,
                'idle_configured'  => $hasIdle,
                'server_time'      => now()->toIso8601String(),
            ],
        ]);
    }
}
