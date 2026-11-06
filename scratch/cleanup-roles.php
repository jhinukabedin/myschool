<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

App\Models\Role::query()->where('slug', 'qa-data-entry')->delete();
echo 'temp role removed: '.(App\Models\Role::where('slug', 'qa-data-entry')->exists() ? 'no' : 'yes').PHP_EOL;

$app->make(Illuminate\Contracts\Console\Kernel::class)->call('db:seed', [
    '--class' => 'Database\\Seeders\\RolePermissionSeeder',
    '--force' => true,
]);

echo 'teacher permissions: '.App\Models\Role::where('slug', 'teacher')->first()->permissions()->count().PHP_EOL;
