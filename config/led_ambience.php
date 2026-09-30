<?php

return [
    'media_path' => env('EIGER_MEDIA_PATH') ?: storage_path('app/eiger-media'),
    'max_video_mb' => (int) env('LED_AMBIENCE_MAX_VIDEO_MB', 500),
    'max_audio_mb' => (int) env('LED_AMBIENCE_MAX_AUDIO_MB', 100),
    'templates' => [
        'idle' => ['name' => 'Idle / Standby', 'sort_order' => 0, 'lighting_color' => '#e8500a'],
        'mountaineering' => ['name' => 'Mountaineering', 'sort_order' => 10, 'lighting_color' => '#2563eb'],
        'lifestyle' => ['name' => 'Lifestyle', 'sort_order' => 20, 'lighting_color' => '#16a34a'],
        'tactical' => ['name' => 'Tactical', 'sort_order' => 30, 'lighting_color' => '#64748b'],
        'riding' => ['name' => 'Riding', 'sort_order' => 40, 'lighting_color' => '#dc2626'],
    ],
];
