<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;

// 1) Boot log
add_action(HookPoints::CMS_BOOTED, function () {
    logger()->info('Hello plugin booted');
});

// 2) Enqueue assets
add_action(HookPoints::CMS_ENQUEUE_ASSETS, function () {
    app(\App\Cms\Plugins\PluginManager::class)->enqueuePluginAssets('hello', 'frontend');
});

// 3) Shortcode: [hello]
add_action(HookPoints::CMS_BOOTED, function () {
    $shortcodes = app(\App\Cms\Content\Shortcodes\ShortcodeRegistry::class);

    $shortcodes->register('hello', function (array $attrs, ?string $content) {
        $settings = app(Settings::class);

        // If plugin disabled from settings, show nothing
        $enabled = (bool) $settings->get('enabled', true, 'plugin:hello');
        if (!$enabled) {
            return '';
        }

        $defaultMsg = (string) $settings->get('message', 'Hello from plugin!', 'plugin:hello');

        $name = (string)($attrs['name'] ?? '');
        $text = $content ?: ($name !== '' ? "Hello {$name}!" : $defaultMsg);

        return '<span class="hello-plugin">'.e($text).'</span>';
    });
});
