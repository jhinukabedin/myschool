<?php

/**
 * Verifies individual menu switches: a menu switched off is closed for school
 * administrators even when its module is enabled, and switching it on reopens it.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SchoolSetting;

const BASE = 'http://newschool.test';

$passed = 0;
$failed = 0;
$jar = '';

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  [OK  ] $label\n";
    } else {
        $failed++;
        echo "  [FAIL] $label".($detail !== '' ? " ($detail)" : '')."\n";
    }
}

function req(string $method, string $path, array $form = []): array
{
    global $jar;
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form, '', '&'));
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

function login(string $path, string $email, string $password, string $jarPath): void
{
    global $jar;
    $jar = $jarPath;
    @unlink($jarPath);
    $page = req('GET', $path);
    req('POST', $path, ['_token' => token($page['body']), 'email' => $email, 'password' => $password]);
}

/**
 * Save the subscription form the way the browser does: modules, menus in scope.
 */
function save(array $modules, array $scope, array $menus): array
{
    $page = req('GET', '/admin/subscription');
    $form = ['_token' => token($page['body']), '_method' => 'PUT', 'modules' => $modules, 'menu_scope' => $scope, 'menus' => $menus];

    return req('POST', '/admin/subscription', $form);
}

function setting(string $key): ?string
{
    return SchoolSetting::query()->where('key', $key)->value('value');
}

login('/login', 'admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-ms-super.jar');
check('super admin signs in', true);

// Enable fees; both fee menus start on, then switch structures off.
save(['fees'], ['fee-structure', 'fee-collection'], ['fee-collection']);
check('structures menu saved as off', setting('menu_fee-structure') === '0', (string) setting('menu_fee-structure'));
check('collection menu stays on', setting('menu_fee-collection') === '1');

login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-ms-admin.jar');
$structures = req('GET', route('fees.structures.index', [], false));
check('switched-off menu is closed for the school admin', $structures['status'] === 403, (string) $structures['status']);
$collection = req('GET', route('fees.collection.index', [], false));
check('switched-on menu opens for the school admin', $collection['status'] === 200, (string) $collection['status']);
$dash = req('GET', '/dashboard');
check('switched-off menu stays visible as locked', str_contains($dash['body'], 'Fee Structure is turned off by the super administrator'));
check('switched-off menu is not a working link', ! str_contains($dash['body'], route('fees.structures.index', [], false).'"'));
check('switched-on menu is in the sidebar', str_contains($dash['body'], route('fees.collection.index', [], false).'"'));

// Super admin reopens the menu.
login('/login', 'admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-ms-super.jar');
save(['fees'], ['fee-structure', 'fee-collection'], ['fee-structure', 'fee-collection']);
check('structures menu switched back on', setting('menu_fee-structure') === '1');

login('/login', 'school.admin@edusphere.test', 'SchoolAdmin@12345', __DIR__.'/cookie-ms-admin.jar');
$structures = req('GET', route('fees.structures.index', [], false));
check('reopened menu opens for the school admin', $structures['status'] === 200, (string) $structures['status']);

// Locking the module keeps the menu setting for later.
login('/login', 'admin@edusphere.test', 'Admin@12345', __DIR__.'/cookie-ms-super.jar');
save([], [], []);
check('module lock does not change menu switches', setting('menu_fee-structure') === '1');

// Cleanup.
SchoolSetting::query()->where('key', 'like', 'menu_%')->delete();
foreach (array_keys(config('school.modules')) as $key) {
    if (! config("school.modules.$key.core")) {
        SchoolSetting::put("module_{$key}", '0', 'subscription');
    }
}
@unlink(__DIR__.'/cookie-ms-super.jar');
@unlink(__DIR__.'/cookie-ms-admin.jar');

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
