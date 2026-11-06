<?php

/**
 * Verifies the multi-item expense invoice:
 *  - form renders add/remove rows and the live total
 *  - server recalculates the total from the submitted items
 *  - items are stored and editable
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie-expense.jar';

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
    $headers = ['Accept: text/html'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE, CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60,
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

$session = current_session();
$category = ExpenseCategory::query()->firstOrFail();

echo "\n=== Form ===\n";
$form = req('GET', '/expenses/create');
check('form loads', $form['status'] === 200, 'status '.$form['status']);
check('has add item control', str_contains($form['body'], 'Add item'));
check('has items editor component', str_contains($form['body'], 'expenseItems('));
check('has quantity column', str_contains($form['body'], 'Unit price'));
check('total is bound to the items', str_contains($form['body'], 'x-bind:value="total.toFixed(2)"'));

echo "\n=== Store a multi-item invoice ===\n";
$payload = [
    '_token' => token($form['body']),
    'title' => 'Multi item invoice test',
    'expense_category_id' => $category->id,
    'academic_session_id' => $session->id,
    'expense_date' => now()->toDateString(),
    'payment_method' => 'cash',
    'items' => [
        ['description' => 'A4 paper ream', 'quantity' => 3, 'unit_price' => 250],
        ['description' => 'Printer toner', 'quantity' => 1, 'unit_price' => 1200.50],
        ['description' => 'Delivery charge', 'quantity' => 2, 'unit_price' => 75.25],
    ],
];

$store = req('POST', '/expenses', $payload);
check('store redirects', in_array($store['status'], [302, 303], true), 'status '.$store['status']);
check('redirects to the list (not back to the form)', str_contains($store['headers'], '/expenses') && ! str_contains($store['headers'], '/expenses/create'), trim($store['headers']));

$expense = Expense::with('items')->where('title', 'Multi item invoice test')->latest('id')->first();
check('expense created', $expense !== null);

if ($expense) {
    // 3*250 = 750 ; 1200.50 ; 2*75.25 = 150.50 => 2101.00
    $expected = 750 + 1200.50 + 150.50;
    check('three items stored', $expense->items->count() === 3, (string) $expense->items->count());
    check('line amount computed (3 × 250)', (float) $expense->items[0]->amount === 750.0, (string) $expense->items[0]->amount);
    check('invoice total recalculated', abs((float) $expense->amount - $expected) < 0.01, (string) $expense->amount);
    check('items keep their order', $expense->items->pluck('description')->all() === ['A4 paper ream', 'Printer toner', 'Delivery charge']);

    echo "\n=== Edit keeps items ===\n";
    $edit = req('GET', '/expenses/'.$expense->id.'/edit');
    check('edit form loads', $edit['status'] === 200, 'status '.$edit['status']);
    check('existing items prefilled', str_contains($edit['body'], 'A4 paper ream') && str_contains($edit['body'], 'Printer toner'));

    echo "\n=== Update replaces items and total ===\n";
    $update = req('POST', '/expenses/'.$expense->id, [
        '_token' => token($edit['body']),
        '_method' => 'PUT',
        'title' => 'Multi item invoice test',
        'expense_category_id' => $category->id,
        'academic_session_id' => $session->id,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'bank',
        'items' => [
            ['description' => 'Only one item now', 'quantity' => 4, 'unit_price' => 100],
        ],
    ]);
    check('update redirects', in_array($update['status'], [302, 303], true), 'status '.$update['status']);

    $expense->refresh()->load('items');
    check('items replaced', $expense->items->count() === 1, (string) $expense->items->count());
    check('total follows the new item', abs((float) $expense->amount - 400.0) < 0.01, (string) $expense->amount);

    echo "\n=== Validation ===\n";
    $bad = req('POST', '/expenses', [
        '_token' => token(req('GET', '/expenses/create')['body']),
        'title' => 'No items',
        'expense_category_id' => $category->id,
        'academic_session_id' => $session->id,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'items' => [['description' => '', 'quantity' => 1, 'unit_price' => '']],
    ]);
    check('empty item row is rejected', $bad['status'] === 302 && ! Expense::where('title', 'No items')->exists());

    // Cleanup
    DB::table('expense_items')->where('expense_id', $expense->id)->delete();
    DB::table('expenses')->where('id', $expense->id)->delete();
    DB::table('audit_logs')->where('description', 'like', '%Multi item invoice test%')->delete();
    echo "  cleaned up test expense #{$expense->id}\n";
}

echo "\n=== List shows item counts ===\n";
$list = req('GET', '/expenses');
check('list loads', $list['status'] === 200, 'status '.$list['status']);
check('list has Items column', str_contains($list['body'], '>Items<'));

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
