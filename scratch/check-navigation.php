<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$items = App\Support\Navigation::items();
$keys = array_column($items, 'key');
$mods = array_keys(config('school.modules'));
$bad = [];

echo 'items: '.count($items).PHP_EOL;
echo 'dupes: '.implode(',', array_diff_assoc($keys, array_unique($keys))).PHP_EOL;

foreach ($items as $item) {
    $module = $item['module'] ?? '';
    if (! in_array($module, $mods, true) && $module !== 'core') {
        $bad[] = $item['key'].':module '.$module;
    }

    $route = $item['route'];
    $routeName = is_array($route) ? $route[0] : $route;
    if (! Illuminate\Support\Facades\Route::has($routeName)) {
        $bad[] = $item['key'].':route '.$routeName;
    }

    if (App\Support\Navigation::find($routeName)['key'] !== $item['key']) {
        $bad[] = $item['key'].':owns '.App\Support\Navigation::find($routeName)['key'];
    }
}

echo 'bad: '.implode(', ', $bad).PHP_EOL;

foreach (config('school.modules') as $key => $meta) {
    echo $key.' => '.count(App\Support\Navigation::forModule($key)).PHP_EOL;
}
