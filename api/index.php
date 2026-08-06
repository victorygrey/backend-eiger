<?php

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

putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
putenv('APP_PACKAGES_CACHE=/tmp/storage/bootstrap/cache/packages.php');
putenv('APP_SERVICES_CACHE=/tmp/storage/bootstrap/cache/services.php');
$_ENV['VIEW_COMPILED_PATH'] = '/tmp/storage/framework/views';
$_ENV['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
$_ENV['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';

foreach ($_SERVER as $key => $value) {
    if (is_string($value) && getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

putenv('DB_CONNECTION=pgsql');
putenv('DB_SSLMODE=require');

if ($databaseUrl = getenv('DATABASE_URL')) {
    $url = parse_url($databaseUrl);
    if ($url && isset($url['host'])) {
        putenv('DB_HOST=' . $url['host']);
        putenv('DB_PORT=' . ($url['port'] ?? '5432'));
        putenv('DB_DATABASE=' . trim($url['path'] ?? '', '/'));
        putenv('DB_USERNAME=' . ($url['user'] ?? ''));
        putenv('DB_PASSWORD=' . ($url['pass'] ?? ''));
        
        $_ENV['DB_HOST'] = $url['host'];
        $_ENV['DB_PORT'] = $url['port'] ?? '5432';
        $_ENV['DB_DATABASE'] = trim($url['path'] ?? '', '/');
        $_ENV['DB_USERNAME'] = $url['user'] ?? '';
        $_ENV['DB_PASSWORD'] = $url['pass'] ?? '';
    }
}

require __DIR__ . '/../public/index.php';
