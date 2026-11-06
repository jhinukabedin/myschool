<?php

/**
 * Hits every Website Management screen and reports non-200 responses.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-wadmin.jar';

function req(string $method, string $path, array $form = []): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_TIMEOUT => 60,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}
function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

@unlink(COOKIE);
$login = req('GET', '/login');
req('POST', '/login', ['_token' => token($login['body']), 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);

$routes = collect(Route::getRoutes())->filter(function ($route) {
    return str_starts_with((string) $route->getName(), 'website-admin.')
        || str_starts_with((string) $route->getName(), 'website-content.')
        || str_starts_with((string) $route->getName(), 'website-menus.')
        || str_starts_with((string) $route->getName(), 'media.');
})->filter(fn ($route) => in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{'));

$bad = 0;

foreach ($routes as $route) {
    $res = req('GET', '/'.$route->uri());
    $ok = $res['status'] === 200;
    if (! $ok) {
        $bad++;
        echo '[FAIL '.$res['status'].'] '.$route->getName().' ('.$route->uri().')'.PHP_EOL;
    }
}

echo PHP_EOL.'checked '.$routes->count().' GET routes, failures: '.$bad.PHP_EOL;
