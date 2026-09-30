<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceTemplate;
use App\Models\RfidTag;
use App\Services\LedAmbienceActivityClassifier;
use App\Support\DeviceProductPayload;
use App\Support\PimMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedAmbienceApiController extends Controller
{
    public function idle(): JsonResponse
    {
        $template = LedAmbienceTemplate::active()->where('template_key', 'idle')->first();

        if (! $template) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template Idle / Standby belum aktif.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->templatePayload($template),
        ]);
    }

    /**
     * Kept on the existing URL for device compatibility. It now returns the five global templates.
     */
    public function scenes(): JsonResponse
    {
        $templates = LedAmbienceTemplate::active()->ordered()->get();

        return response()->json([
            'status' => 'success',
            'count' => $templates->count(),
            'data' => $templates->map(fn (LedAmbienceTemplate $template): array => $this->templatePayload($template)),
        ]);
    }

    public function trigger(Request $request, LedAmbienceActivityClassifier $classifier): JsonResponse
    {
        $validated = $request->validate(['rfid_tag' => ['required', 'string', 'max:64']]);
        $rfidTag = RfidTag::canonicalUid($validated['rfid_tag']);

        $item = LedAmbienceItem::with(array_map(
            fn (string $relation): string => 'product.'.$relation,
            DeviceProductPayload::relations(),
        ))
            ->where('rfid_tag', $rfidTag)
            ->where('is_active', true)
            ->first();

        if (! $item || ! $item->product) {
            return response()->json([
                'status' => 'not_found',
                'matched' => false,
                'message' => "Tag RFID '{$rfidTag}' tidak aktif pada modul LED Ambience.",
                'rfid_tag' => $rfidTag,
            ], 404);
        }

        $classification = $classifier->classify($item->product);
        $templateKey = $classification['key'] ?? 'idle';
        $template = LedAmbienceTemplate::active()->where('template_key', $templateKey)->first();
        $fallbackToIdle = false;

        if (! $template && $templateKey !== 'idle') {
            $template = LedAmbienceTemplate::active()->where('template_key', 'idle')->first();
            $fallbackToIdle = true;
        }

        if (! $template) {
            return response()->json([
                'status' => 'error',
                'matched' => true,
                'message' => "Template LED Ambience '{$templateKey}' belum aktif dan template Idle tidak tersedia.",
                'data' => null,
            ], 409);
        }

        $item->update(['last_scanned_at' => now()]);
        RfidTag::recordScan($rfidTag);

        return response()->json([
            'status' => 'success',
            'matched' => true,
            'source' => 'dominant_activity_template',
            'data' => [
                'rfid_tag' => $item->rfid_tag,
                'activity_group' => $classification,
                'activity' => $classification,
                'fallback_to_idle' => $fallbackToIdle || $classification === null,
                'product' => DeviceProductPayload::make($item->product),
                'scene' => $this->templatePayload($template),
            ],
        ]);
    }

    public function itemLost(): JsonResponse
    {
        $template = LedAmbienceTemplate::active()->where('template_key', 'idle')->first();

        return response()->json([
            'status' => 'success',
            'event' => 'item_lost',
            'message' => 'Layar kembali ke mode suasana Idle (standby).',
            'data' => ['scene' => $template ? $this->templatePayload($template) : null],
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'active_rfid_items' => LedAmbienceItem::where('is_active', true)->count(),
                'total_templates' => LedAmbienceTemplate::count(),
                'active_templates' => LedAmbienceTemplate::active()->count(),
                'configured_video_templates' => LedAmbienceTemplate::whereNotNull('video_url')->count(),
                'configured_audio_templates' => LedAmbienceTemplate::whereNotNull('audio_url')->count(),
                'idle_configured' => LedAmbienceTemplate::active()->where('template_key', 'idle')->exists(),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function templatePayload(LedAmbienceTemplate $template): array
    {
        return [
            'id' => $template->id,
            'template_key' => $template->template_key,
            'name' => $template->name,
            'scene_type' => $template->template_key === 'idle' ? 'idle' : 'active',
            'activity_slug' => $template->template_key === 'idle' ? null : $template->template_key,
            'video_url' => PimMediaUrl::toPublicUrl($template->video_url),
            'audio_url' => PimMediaUrl::toPublicUrl($template->audio_url),
            'lighting_color' => $template->lighting_color,
            'description' => $template->description,
            'is_active' => $template->is_active,
        ];
    }
}
