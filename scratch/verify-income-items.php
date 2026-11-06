<?php

/**
 * Verifies multi-item Other Income entries mirror the expense invoice flow.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-income.jar';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK  ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}
function req(string $method, string $path, array $form = []): array
{
    $ch = curl_init(BASE.$path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => ['Accept: text/html'], CURLOPT_TIMEOUT => 60,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
}
function token(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

@unlink(COOKIE);
$login = req('GET', '/login');
req('POST', '/login', ['_token' => token($login['body']), 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345']);

$category = OtherIncomeCategory::query()->first();
$session = current_session();

echo "\n=== Form ===\n";
$form = req('GET', '/other-incomes/create');
check('form loads', $form['status'] === 200, 'status '.$form['status']);
check('has add item control', str_contains($form['body'], 'Add item'));
check('has items editor component', str_contains($form['body'], 'incomeItems('));
check('has unit price column', str_contains($form['body'], 'Unit price'));
check('total bound to items', str_contains($form['body'], 'x-bind:value="total.toFixed(2)"'));

echo "\n=== Store a multi-item income ===\n";
$store = req('POST', '/other-incomes', [
    '_token' => token($form['body']),
    'title' => 'Multi item income test',
    'income_date' => now()->toDateString(),
    'other_income_category_id' => $category?->id,
    'reference' => 'INC-TEST-1',
    'items' => [
        ['description' => 'Alumni donation', 'quantity' => 2, 'unit_price' => 1500],
        ['description' => 'Hall rent', 'quantity' => 1, 'unit_price' => 2500.75],
        ['description' => 'Book sale', 'quantity' => 5, 'unit_price' => 120.10],
    ],
]);
check('store redirects', in_array($store['status'], [302, 303], true), 'status '.$store['status']);

$income = OtherIncome::with('items')->where('title', 'Multi item income test')->latest('id')->first();
check('income created', $income !== null);

if ($income) {
    // 2*1500 = 3000 ; 2500.75 ; 5*120.10 = 600.50 => 6101.25
    $expected = 3000 + 2500.75 + 600.50;
    check('three items stored', $income->items->count() === 3, (string) $income->items->count());
    check('line amount computed (5 × 120.10)', (float) $income->items[2]->amount === 600.50, (string) $income->items[2]->amount);
    check('total recalculated', abs((float) $income->amount - $expected) < 0.01, (string) $income->amount);
    check('items keep order', $income->items->pluck('description')->all() === ['Alumni donation', 'Hall rent', 'Book sale']);

    echo "\n=== Edit ===\n";
    $edit = req('GET', '/other-incomes/'.$income->id.'/edit');
    check('edit loads', $edit['status'] === 200, 'status '.$edit['status']);
    check('items prefilled', str_contains($edit['body'], 'Alumni donation') && str_contains($edit['body'], 'Book sale'));

    echo "\n=== Update replaces items ===\n";
    $update = req('POST', '/other-incomes/'.$income->id, [
        '_token' => token($edit['body']),
        '_method' => 'PUT',
        'title' => 'Multi item income test',
        'income_date' => now()->toDateString(),
        'other_income_category_id' => $category?->id,
        'items' => [
            ['description' => 'Single receipt now', 'quantity' => 3, 'unit_price' => 99.5],
        ],
    ]);
    check('update redirects', in_array($update['status'], [302, 303], true), 'status '.$update['status']);

    $income->refresh()->load('items');
    check('items replaced', $income->items->count() === 1, (string) $income->items->count());
    check('total follows new item', abs((float) $income->amount - 298.5) < 0.01, (string) $income->amount);

    echo "\n=== Validation ===\n";
    $bad = req('POST', '/other-incomes', [
        '_token' => token(req('GET', '/other-incomes/create')['body']),
        'title' => 'No item income',
        'income_date' => now()->toDateString(),
        'items' => [['description' => '', 'quantity' => 1, 'unit_price' => '']],
    ]);
    check('empty row rejected', ! OtherIncome::where('title', 'No item income')->exists(), 'status '.$bad['status']);

    DB::table('other_income_items')->where('other_income_id', $income->id)->delete();
    DB::table('other_incomes')->where('id', $income->id)->delete();
    DB::table('audit_logs')->where('description', 'like', '%Multi item income test%')->delete();
    echo "  cleaned up test income #{$income->id}\n";
}

echo "\n=== List ===\n";
$list = req('GET', '/other-incomes');
check('list loads', $list['status'] === 200, 'status '.$list['status']);
check('list has Items column', str_contains($list['body'], '>Items<'));

echo "\n=== Data integrity ===\n";
$mismatch = 0;
foreach (OtherIncome::with('items')->get() as $row) {
    if ($row->items->isEmpty() || abs((float) $row->items->sum('amount') - (float) $row->amount) > 0.01) {
        $mismatch++;
    }
}
check('every income matches its item total', $mismatch === 0, "{$mismatch} mismatch(es)");

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
