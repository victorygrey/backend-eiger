<?php

return [
    'base_url' => env('ATOM_API_URL', 'https://api-eiger-atom.eigeradventure.com/api/v1/app'),
    'catalog_page_size' => (int) env('ATOM_CATALOG_PAGE_SIZE', 250),
    'connect_timeout' => (int) env('ATOM_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('ATOM_TIMEOUT', 120),
    'attempts' => (int) env('ATOM_DOWNLOAD_ATTEMPTS', 4),
    'verify_tls' => filter_var(env('ATOM_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
    'jpg_quality' => (int) env('ATOM_JPG_QUALITY', 92),
    'media_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', strtolower(env('ATOM_MEDIA_HOSTS', 'd1yutv2xslo29o.cloudfront.net')))
    ))),
];
