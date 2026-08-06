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
    if (is_string($value)) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

$databaseUrl = $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: false;

if ($databaseUrl) {
    $url = parse_url($databaseUrl);
    if ($url && isset($url['host'])) {
        $dbVars = [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $url['host'],
            'DB_PORT' => $url['port'] ?? '5432',
            'DB_DATABASE' => trim($url['path'] ?? '', '/'),
            'DB_USERNAME' => $url['user'] ?? '',
            'DB_PASSWORD' => $url['pass'] ?? '',
            'DB_SSLMODE' => 'require',
        ];
        foreach ($dbVars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
} elseif (!getenv('DB_HOST')) {
    putenv('DB_CONNECTION=pgsql');
    $_ENV['DB_CONNECTION'] = 'pgsql';
    $_SERVER['DB_CONNECTION'] = 'pgsql';
}

require __DIR__ . '/../public/index.php';
