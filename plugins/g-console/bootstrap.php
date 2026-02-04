<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use App\Cms\Hooks\Hooks;
use Filament\Panel;

$hooks = app(Hooks::class);

$shouldSkip = function (): bool {
    $path = ltrim(request()->path(), '/');
    return $path === 'admin' || str_starts_with($path, 'admin/');
};

$get = function (string $key): string {
    $settings = app(Settings::class);
    $val = $settings->get($key, '', 'plugins.g-console');

    return is_string($val) ? trim($val) : '';
};

// Inject into <head>
$hooks->addFilter('theme.head', function (string $html) use ($shouldSkip, $get): string {
    if ($shouldSkip()) {
        return $html;
    }

    $meta = $get('header_meta');
    if ($meta === '') {
        return $html;
    }

    return $html . "\n<!-- G Console: Header Meta -->\n" . $meta . "\n<!-- /G Console -->\n";
});

// Inject before </body>
$hooks->addFilter('theme.body.after', function (string $html) use ($shouldSkip, $get): string {
    if ($shouldSkip()) {
        return $html;
    }

    $script = $get('footer_script');
    if ($script === '') {
        return $html;
    }

    return $html . "\n<!-- G Console: Footer Script -->\n" . $script . "\n<!-- /G Console -->\n";
});

// ✅ Register Filament page at PANEL BUILD TIME (correct timing, routes exist)
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel): void {
    $viewsPath = base_path('plugins/g-console/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('g-console', $viewsPath);
    }

    // ✅ Autoload if available, fallback to manual require on shared hosting
    $pageClass = \Plugins\GConsole\Filament\Pages\GConsole::class;

    if (!class_exists($pageClass)) {
        $file = base_path('plugins/g-console/src/Filament/Pages/GConsole.php');

        if (is_file($file)) {
            require_once $file;
        }
    }

    // ✅ Prevent fatal error if class still missing
    if (class_exists($pageClass)) {
        $panel->pages([$pageClass]);
    }
});