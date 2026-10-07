<?php

return [
    'folder' => env('PIM_FOLDER') ?: storage_path('app/pim-drop'),
    'media_path' => env('EIGER_MEDIA_PATH') ?: storage_path('app/eiger-media'),
    'legacy_media_path' => env('PIM_LEGACY_MEDIA_PATH') ?: storage_path('app/pim-media'),
    'max_image_bytes' => (int) env('PIM_MEDIA_MAX_IMAGE_MB', 10) * 1024 * 1024,
    'max_video_bytes' => (int) env('PIM_MEDIA_MAX_VIDEO_MB', 250) * 1024 * 1024,
    'media_connect_timeout' => (int) env('PIM_MEDIA_CONNECT_TIMEOUT', 10),
    'media_download_attempts' => (int) env('PIM_MEDIA_DOWNLOAD_ATTEMPTS', 4),
    // Device APIs generate a reusable WebP derivative for locally stored
    // product photographs. Originals remain untouched for the CMS/PIM archive.
    'device_image_webp_quality' => (int) env('PIM_DEVICE_IMAGE_WEBP_QUALITY', 80),
    'device_image_max_edge' => (int) env('PIM_DEVICE_IMAGE_MAX_EDGE', 1600),
    // Interactive Tablet uses a smaller, lower-quality rendition. "30 DPI"
    // is represented as a delivery profile: WebP quality 30, max 1024px edge.
    // DPI metadata is not used by tablet browsers to determine download size.
    'tablet_image_webp_quality' => (int) env('PIM_TABLET_IMAGE_WEBP_QUALITY', 30),
    'tablet_image_max_edge' => (int) env('PIM_TABLET_IMAGE_MAX_EDGE', 1024),
    'copy_http_media' => env('PIM_COPY_HTTP_MEDIA', false),
    'media_hosts' => array_values(array_filter(array_map('trim', explode(',', strtolower(env('PIM_MEDIA_HOSTS', 'storage.eigeradventure.com,pim-development-932708080162-ap-southeast-3-an.s3.ap-southeast-3.amazonaws.com,d1yutv2xslo29o.cloudfront.net')))))),
    'scan_enabled' => env('PIM_SCAN_ENABLED', true),
    'legacy_http_enabled' => env('PIM_LEGACY_HTTP_ENABLED', false),
    'url' => env('PIM_SIMULATOR_URL', 'http://127.0.0.1:8001'),
    'timeout' => (int) env('PIM_TIMEOUT', 10),
    'inbound_token' => env('PIM_INBOUND_TOKEN'),
];
