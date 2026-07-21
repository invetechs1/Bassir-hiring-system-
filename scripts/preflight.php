<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

function check(bool $ok, string $label, string $details = ''): array
{
    return ['ok' => $ok, 'label' => $label, 'details' => $details];
}

$checks[] = check(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP version >= 8.2', PHP_VERSION);

$requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'zip'];
foreach ($requiredExtensions as $extension) {
    $checks[] = check(extension_loaded($extension), "PHP extension: {$extension}");
}

$writablePaths = [
    $root.'/storage',
    $root.'/bootstrap/cache',
    $root.'/storage/app/private',
];
foreach ($writablePaths as $path) {
    $checks[] = check(is_dir($path) && is_writable($path), "Writable path: {$path}");
}

$envFile = $root.'/.env';
$checks[] = check(file_exists($envFile), '.env exists', $envFile);
$checks[] = check(file_exists($root.'/vendor/autoload.php'), 'Composer vendor/autoload.php exists');

$publicHtaccess = $root.'/public/.htaccess';
if (file_exists($publicHtaccess)) {
    $perms = substr(sprintf('%o', fileperms($publicHtaccess)), -3);
    $checks[] = check(is_readable($publicHtaccess), 'public/.htaccess is readable', $perms);
    $checks[] = check($perms === '644' || $perms === '664', 'public/.htaccess shared-hosting permissions', $perms);
} else {
    $checks[] = check(false, 'public/.htaccess exists');
}

if (file_exists($envFile)) {
    $envText = (string) file_get_contents($envFile);
    $checks[] = check(str_contains($envText, 'APP_KEY=') && ! str_contains($envText, 'APP_KEY='."\n"), 'APP_KEY configured');
    $checks[] = check(str_contains($envText, 'APP_DEBUG=false'), 'APP_DEBUG=false');
    $checks[] = check(
        str_contains($envText, 'LOG_STACK_CHANNEL=daily'),
        'LOG_STACK_CHANNEL=daily (recommended for production log rotation)'
    );
    if (preg_match('/LOG_DAILY_DAYS=(\d+)/', $envText, $dailyDaysMatch) === 1) {
        $checks[] = check(((int) $dailyDaysMatch[1]) >= 7, 'LOG_DAILY_DAYS >= 7', $dailyDaysMatch[1]);
    }
    if (preg_match('/MOBILE_TOKEN_TTL_DAYS=(\d+)/', $envText, $mobileTtlMatch) === 1) {
        $ttlDays = (int) $mobileTtlMatch[1];
        $checks[] = check($ttlDays >= 1 && $ttlDays <= 90, 'MOBILE_TOKEN_TTL_DAYS between 1 and 90', (string) $ttlDays);
    }
    if (preg_match('/APP_URL=(.+)/', $envText, $appUrlMatch) === 1) {
        $appUrl = trim($appUrlMatch[1], "\"' ");
        if (str_starts_with(strtolower($appUrl), 'https://')) {
            $checks[] = check(
                str_contains($envText, 'SESSION_SECURE_COOKIE=true'),
                'SESSION_SECURE_COOKIE=true when APP_URL is HTTPS'
            );
            $checks[] = check(
                str_contains($envText, 'APP_FORCE_HTTPS=true'),
                'APP_FORCE_HTTPS=true when APP_URL is HTTPS'
            );
            $checks[] = check(
                preg_match('/TRUSTED_HOSTS=.+/', $envText) === 1,
                'TRUSTED_HOSTS configured when APP_URL is HTTPS'
            );
            $trustedProxiesConfigured = preg_match('/TRUSTED_PROXIES=.+/', $envText) === 1;
            $proxyHttpsHeadersEnabled = str_contains($envText, 'APP_TRUST_PROXY_HTTPS_HEADERS=true');
            $checks[] = check(
                $trustedProxiesConfigured || $proxyHttpsHeadersEnabled,
                'Proxy HTTPS handling configured for HTTPS APP_URL',
                'set TRUSTED_PROXIES=* or APP_TRUST_PROXY_HTTPS_HEADERS=true when behind Cloudflare/proxy'
            );
        }
    }
}

$pass = 0;
$fail = 0;
foreach ($checks as $item) {
    if ($item['ok']) {
        $pass++;
    } else {
        $fail++;
    }
}

echo "Bassir Shared Hosting Preflight\n";
echo "================================\n";
foreach ($checks as $item) {
    $icon = $item['ok'] ? '[OK]' : '[FAIL]';
    echo "{$icon} {$item['label']}";
    if (! empty($item['details'])) {
        echo " ({$item['details']})";
    }
    echo PHP_EOL;
}
echo "--------------------------------\n";
echo "Passed: {$pass}\n";
echo "Failed: {$fail}\n";
echo "================================\n";

exit($fail > 0 ? 1 : 0);
