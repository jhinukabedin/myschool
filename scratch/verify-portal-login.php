<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\Models\User;
use App\Services\LoginIdentifierResolver;

$resolver = app(LoginIdentifierResolver::class);

$withPortal = Student::query()
    ->whereNotNull('student_code')
    ->whereHas('portalLink')
    ->with('portalLink.user')
    ->first();

if (! $withPortal) {
    echo "No student has a portal account in this dataset — login test skipped.\n";
    exit(0);
}

echo 'Student: '.$withPortal->full_name.' | ID: '.$withPortal->student_code.PHP_EOL;
echo 'Portal username: '.($withPortal->portalLink?->user?->username ?? '—').PHP_EOL;

$resolved = $resolver->resolve($withPortal->student_code, 'student');
echo 'Resolve by Student ID => '.($resolved ? 'OK (user #'.$resolved->id.')' : 'FAILED').PHP_EOL;

// Also confirm the admin account still resolves (no regression).
$admin = User::where('email', 'admin@edusphere.test')->first();
echo 'Admin email resolves => '.($resolver->resolve('admin@edusphere.test') ? 'OK' : 'FAILED').PHP_EOL;
