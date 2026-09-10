<?php

return [
    'folder' => env('PIM_FOLDER') ?: storage_path('app/pim-drop'),
    'media_path' => storage_path('app/pim-media'),
    'copy_http_media' => env('PIM_COPY_HTTP_MEDIA', false),
    'media_hosts' => array_values(array_filter(array_map('trim', explode(',', strtolower(env('PIM_MEDIA_HOSTS', 'storage.eigeradventure.com,pim-development-932708080162-ap-southeast-3-an.s3.ap-southeast-3.amazonaws.com')))))),
    'scan_enabled' => env('PIM_SCAN_ENABLED', true),
    'legacy_http_enabled' => env('PIM_LEGACY_HTTP_ENABLED', false),
    'url' => env('PIM_SIMULATOR_URL', 'http://127.0.0.1:8001'),
    'timeout' => (int) env('PIM_TIMEOUT', 10),
    'inbound_token' => env('PIM_INBOUND_TOKEN'),
];
