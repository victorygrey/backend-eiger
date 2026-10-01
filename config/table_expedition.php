<?php

return [
    'media_path' => env('EIGER_MEDIA_PATH') ?: storage_path('app/eiger-media'),
    'max_image_mb' => (int) env('TABLE_EXPEDITION_MAX_IMAGE_MB', 15),
    'max_video_mb' => (int) env('TABLE_EXPEDITION_MAX_VIDEO_MB', 500),
];
