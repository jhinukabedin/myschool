<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Role::withCount('users')->orderBy('name')->get() as $role) {
    echo $role->id.' | '.$role->slug.' | '.$role->name.' | '.$role->users_count.' | '.$role->description.PHP_EOL;
}

echo '--- permissions by module ---'.PHP_EOL;
foreach (App\Models\Permission::orderBy('module')->orderBy('slug')->get()->groupBy('module') as $module => $rows) {
    echo $module.': '.$rows->pluck('slug')->implode(', ').PHP_EOL;
}
