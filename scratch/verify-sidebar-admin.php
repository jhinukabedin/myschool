<?php

/**
 * End-to-end check of the Website Management sidebar workflow:
 * create menu -> add item -> public sidebar shows it -> cleanup.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WebsiteMenu;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-sidebar.jar';

$passed = 0;
$failed = 0;

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

function req(string $method, string $path, array $form = [], bool $follow = false): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => COOKIE,
        CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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
$res = req('POST', '/login', [
    '_token' => token($login['body']),
    'email' => 'admin@edusphere.test',
    'password' => 'Admin@12345',
]);
check('admin login redirects', $res['status'] === 302, (string) $res['status']);

$page = req('GET', '/admin/website/sidebar');
$token = token($page['body']);
check('sidebar admin page loads', $page['status'] === 200, (string) $page['status']);
check('sidebar page has create-menu form', str_contains($page['body'], 'name="location" value="sidebar"'));

if ($token) {
    $res = req('POST', '/admin/menus', [
        '_token' => $token,
        'name' => 'QA Sidebar E2E',
        'location' => 'sidebar',
        'is_active' => '1',
    ]);
    check('create sidebar menu redirects', $res['status'] === 302, (string) $res['status']);

    $menu = WebsiteMenu::query()->where('name', 'QA Sidebar E2E')->first();
    check('sidebar menu saved', $menu !== null);

    if ($menu) {
        $page = req('GET', '/admin/website/sidebar');
        $token = token($page['body']);
        check('sidebar page shows the new menu', str_contains($page['body'], 'QA Sidebar E2E'));
        check('sidebar page has add-item form', str_contains($page['body'], 'Add menu item'));

        $res = req('POST', "/admin/menus/{$menu->id}/items", [
            '_token' => $token,
            'title' => 'QA Link E2E',
            'url' => '/about',
            'is_active' => '1',
        ]);
        check('add sidebar item redirects', $res['status'] === 302, (string) $res['status']);

        $public = req('GET', '/about');
        check('public about page loads', $public['status'] === 200, (string) $public['status']);
        check('public sidebar heading is the menu name', str_contains($public['body'], 'QA Sidebar E2E'));
        check('public sidebar shows the new item', str_contains($public['body'], 'QA Link E2E'));

        $menu->items()->delete();
        $menu->delete();
        check('cleanup removed the test menu', WebsiteMenu::query()->where('name', 'QA Sidebar E2E')->doesntExist());
    }
}

@unlink(COOKIE);

echo PHP_EOL."=== RESULT: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
