<?php

function commercego_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function commercego_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    commercego_load_env(__DIR__ . '/../.env');

    $localConfigPath = __DIR__ . '/deployment-config.php';
    $localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
    if (!is_array($localConfig)) {
        $localConfig = [];
    }

    $config = [
        'db_host' => getenv('COMMERCEGO_DB_HOST') ?: ($localConfig['db_host'] ?? 'localhost'),
        'db_user' => getenv('COMMERCEGO_DB_USER') ?: ($localConfig['db_user'] ?? 'root'),
        'db_pass' => getenv('COMMERCEGO_DB_PASS') ?: ($localConfig['db_pass'] ?? ''),
        'db_name' => getenv('COMMERCEGO_DB_NAME') ?: ($localConfig['db_name'] ?? 'commercego'),
        'app_url' => rtrim((string) (getenv('COMMERCEGO_APP_URL') ?: ($localConfig['app_url'] ?? 'http://localhost/FYP-CommerceGo')), '/'),
        'stripe_secret_key' => getenv('STRIPE_SECRET_KEY') ?: ($localConfig['stripe_secret_key'] ?? ''),
    ];

    return $config;
}

function commercego_app_url(string $path = ''): string
{
    $config = commercego_config();
    $baseUrl = $config['app_url'];

    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseDir = preg_replace('#/customer$#', '', $scriptDir);
        $baseUrl = $scheme . '://' . $host . rtrim($baseDir, '/');
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function commercego_stripe_secret_key(): string
{
    $config = commercego_config();
    return trim((string) $config['stripe_secret_key']);
}
