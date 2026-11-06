<?php

/**
 * End-to-end check against the served site (http://newschool.test):
 * logs in over HTTP, exercises the admission sibling flow and the per-fee
 * scholarship endpoints, then asserts the resulting database state.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Support\Facades\DB;

const BASE = 'http://newschool.test';
const COOKIE = __DIR__.'/cookie.jar';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo sprintf("  [%s] %s%s\n", $ok ? 'OK ' : 'FAIL', $label, $detail !== '' ? ' — '.$detail : '');
}

function req(string $method, string $path, array $options = []): array
{
    $ch = curl_init(BASE.$path);
    $headers = $options['headers'] ?? [];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => COOKIE,
        CURLOPT_COOKIEFILE => COOKIE,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 40,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['form'] ?? []));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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

// ---------------------------------------------------------------- login
echo "\n=== Login ===\n";
$login = req('GET', '/login');
$csrf = token($login['body']);
check('login page renders CSRF token', $csrf !== null, 'status '.$login['status']);

$after = req('POST', '/login', [
    'form' => ['_token' => $csrf, 'email' => 'admin@edusphere.test', 'password' => 'Admin@12345'],
]);
check('login redirects to dashboard', in_array($after['status'], [302, 303], true), 'status '.$after['status']);

$dash = req('GET', '/dashboard');
check('dashboard loads', $dash['status'] === 200, 'status '.$dash['status']);

// ------------------------------------------------- admission form (English)
echo "\n=== Admission form (English) ===\n";
$adm = req('GET', '/admissions/create');
check('admission page loads', $adm['status'] === 200, 'status '.$adm['status']);
check('sibling dropdown label present', str_contains($adm['body'], 'Brother or sister already admitted?'));
check('sibling search card present', str_contains($adm['body'], 'Sibling Student'));
check('sibling box disabled by default', str_contains($adm['body'], 'Select Yes above to enable'));
check('name (English) field present', str_contains($adm['body'], 'Student name (English)'));
check('name (Bangla) field present', str_contains($adm['body'], 'Student name (Bangla)'));
check('no first/last name label leaked', ! str_contains($adm['body'], 'First name'));
check('admissionSiblings() defined', str_contains($adm['body'], 'function admissionSiblings()'));
check('hidden sibling id input present', str_contains($adm['body'], 'name="sibling_student_id"'));

// ------------------------------------------------- admission form (Bangla)
echo "\n=== Language switch ===\n";
req('GET', '/language/bn');
$bn = req('GET', '/admissions/create');
check('bangla admission page loads', $bn['status'] === 200, 'status '.$bn['status']);
check('bangla sibling label', str_contains($bn['body'], 'ভাই বা বোন ইতিমধ্যে ভর্তি আছে?'));
check('bangla guardian heading', str_contains($bn['body'], 'অভিভাবকের তথ্য'));
check('bangla admit button', str_contains($bn['body'], 'ভর্তি সম্পন্ন করুন'));
req('GET', '/language/en');

// ------------------------------------------------- sibling search endpoint
echo "\n=== Sibling search endpoint ===\n";
$elder = Student::query()
    ->with(['guardians' => fn ($q) => $q->where('is_primary', true)])
    ->where('status', 'active')
    ->whereHas('guardians')
    ->firstOrFail();

$search = req('GET', '/admissions/sibling-search?q='.urlencode(mb_substr($elder->first_name, 0, 3)), [
    'headers' => ['Accept: application/json'],
]);
check('sibling search returns 200 JSON', $search['status'] === 200, 'status '.$search['status']);

$rows = json_decode($search['body'], true);
check('sibling search returns rows', is_array($rows) && count($rows) > 0, 'rows '.(is_array($rows) ? count($rows) : 'null'));

$match = collect($rows ?? [])->firstWhere('id', $elder->id);
check('searched student is present', $match !== null, $elder->full_name);
check('guardian payload included', isset($match['guardian']['phone']) && $match['guardian']['phone'] === $elder->guardians->first()->phone,
    'phone '.($match['guardian']['phone'] ?? 'missing'));

$short = req('GET', '/admissions/sibling-search?q=a', ['headers' => ['Accept: application/json']]);
check('single character search is ignored', json_decode($short['body'], true) === [], $short['body']);

// ------------------------------------------------- submit an admission
echo "\n=== Submit admission with sibling ===\n";
$session = current_session();
$class = $session ? \App\Models\SchoolClass::query()->with('sections')->ordered()->first() : null;
$section = $class?->sections()->first();

if (! $class || ! $section) {
    check('class/section available for admission', false, 'seed data missing');
} else {
    $form = token(req('GET', '/admissions/create')['body']);

    $payload = [
        '_token' => $form,
        'first_name' => 'E2eSibling',
        'last_name' => 'Kid',
        'name_bn' => 'ই২ই ভাই',
        'has_sibling' => '1',
        'sibling_student_id' => $elder->id,
        'gender' => 'male',
        'date_of_birth' => '2016-03-03',
        'academic_session_id' => $session->id,
        'class_id' => $class->id,
        'section_id' => $section->id,
        'admission_date' => now()->toDateString(),
        'guardian_name' => $elder->guardians->first()->name,
        'guardian_relation' => $elder->guardians->first()->relation,
        'guardian_phone' => $elder->guardians->first()->phone,
        'guardian_email' => $elder->guardians->first()->email,
    ];

    $submit = req('POST', '/admissions', ['form' => $payload]);
    check('admission accepted (redirect)', in_array($submit['status'], [302, 303], true), 'status '.$submit['status']);

    $created = Student::query()->where('first_name', 'E2eSibling')->first();
    check('student stored', $created !== null);

    if ($created) {
        check('has_sibling persisted', $created->has_sibling == true, var_export($created->has_sibling, true));
        check('sibling_student_id persisted', (int) $created->sibling_student_id === (int) $elder->id, 'link '.$created->sibling_student_id);
        check('name_bn persisted', $created->name_bn === 'ই২ই ভাই', (string) $created->name_bn);
        check('guardian copied from sibling', $created->primaryGuardian()?->phone === $elder->guardians->first()->phone,
            'phone '.$created->primaryGuardian()?->phone);

        $profile = req('GET', '/students/'.$created->id);
        check('profile shows sibling card', str_contains($profile['body'], 'Siblings') || str_contains($profile['body'], 'ভাই/বোন'), 'status '.$profile['status']);
    }
}

// ------------------------------------------------- per-fee scholarship
echo "\n=== Per-fee scholarship over HTTP ===\n";
$target = Student::query()->where('first_name', 'E2eSibling')->first() ?? $elder;

$row = StudentFeeAssignment::create([
    'student_id' => $target->id,
    'fee_category_id' => FeeCategory::query()->firstOrFail()->id,
    'academic_session_id' => $session->id,
    'period' => null,
    'amount' => 1000,
    'discount_amount' => 0,
    'waiver_amount' => 0,
    'scholarship_amount' => 0,
    'net_amount' => 1000,
    'due_date' => now()->toDateString(),
    'status' => 'pending',
    'note' => 'e2e scholarship row',
]);

$page = req('GET', '/fees/collection/'.$target->id);
check('collection page loads', $page['status'] === 200, 'status '.$page['status']);
check('collection page has scholarship button', str_contains($page['body'], 'Set scholarship'));
check('collection page mentions per-fee scholarship', str_contains($page['body'], 'Scholarship is set per fee') || str_contains($page['body'], 'graduation-cap'));

$csrf = token($page['body']);
$set = req('POST', '/fees/assignments/'.$row->id.'/scholarship', [
    'form' => ['_token' => $csrf, 'scholarship_type' => 'percent', 'scholarship_value' => 25],
]);
check('scholarship endpoint accepts percent', in_array($set['status'], [302, 303], true), 'status '.$set['status']);

$row->refresh();
check('percent scholarship computed', (float) $row->scholarship_amount === 250.0, 'scholarship '.$row->scholarship_amount);
check('net amount reduced', (float) $row->net_amount === 750.0, 'net '.$row->net_amount);

$csrf = token(req('GET', '/fees/collection/'.$target->id)['body']);
req('POST', '/fees/assignments/'.$row->id.'/scholarship', [
    'form' => ['_token' => $csrf, 'scholarship_type' => 'fixed', 'scholarship_value' => 300],
]);
$row->refresh();
check('fixed scholarship replaces percent', (float) $row->scholarship_amount === 300.0 && $row->scholarship_type === 'fixed',
    $row->scholarship_type.' = '.$row->scholarship_amount);

// Fee structure form exposes the scholarship template column.
$structurePage = req('GET', '/fees/structures/create');
check('structure form has scholarship column', str_contains($structurePage['body'], 'Scholarship'), 'status '.$structurePage['status']);
check('structure form has percent/fixed options', str_contains($structurePage['body'], 'Percentage (%)') && str_contains($structurePage['body'], 'Fixed'));

// ------------------------------------------------- cleanup e2e artifacts
echo "\n=== Cleanup ===\n";
DB::table('payment_allocations')->where('student_fee_assignment_id', $row->id)->delete();
DB::table('student_fee_assignments')->where('id', $row->id)->delete();

if (isset($created) && $created) {
    $userIds = array_merge(
        DB::table('student_portal_links')->where('student_id', $created->id)->pluck('user_id')->all(),
        DB::table('parent_student_links')->where('student_id', $created->id)->pluck('user_id')->all()
    );

    DB::table('student_portal_links')->where('student_id', $created->id)->delete();
    DB::table('parent_student_links')->where('student_id', $created->id)->delete();

    // Only drop portal accounts this run created (a shared parent may serve siblings).
    $orphans = DB::table('users')->whereIn('id', $userIds)
        ->whereNotIn('id', DB::table('student_portal_links')->pluck('user_id'))
        ->whereNotIn('id', DB::table('parent_student_links')->pluck('user_id'))
        ->pluck('id')->all();

    if ($orphans) {
        DB::table('model_has_roles')->whereIn('user_id', $orphans)->delete();
        DB::table('users')->whereIn('id', $orphans)->delete();
    }

    DB::table('enrollments')->where('student_id', $created->id)->delete();
    DB::table('student_guardians')->where('student_id', $created->id)->delete();
    DB::table('students')->where('id', $created->id)->delete();
    DB::table('audit_logs')->where('description', 'like', '%E2eSibling%')->delete();
    echo "  removed e2e student #{$created->id} and ".count($orphans)." account(s)\n";
}

echo "\n=== RESULT: {$pass} passed, {$fail} failed ===\n";

exit($fail > 0 ? 1 : 0);
