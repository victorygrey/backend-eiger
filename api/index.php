<?php

// Forward Vercel requests to public index.php after preparing /tmp storage directories
$tmpDirs = [
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/bootstrap/cache',
    '/tmp/storage/logs',
];

foreach ($tmpDirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

foreach ($_SERVER as $key => $value) {
    if (is_string($value) && getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

require __DIR__ . '/../public/index.php';
