<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use Illuminate\Support\Facades\View;

add_action(HookPoints::CMS_BOOTED, function () {
    // ✅ Register plugin views namespace
    View::addNamespace('plugin-hero', base_path('plugins/hero/resources/views'));

    // ✅ Enqueue assets
    add_action(HookPoints::CMS_ENQUEUE_ASSETS, function () {
        app(\App\Cms\Plugins\PluginManager::class)->enqueuePluginAssets('hero', 'frontend');
    });

    // ✅ Inject hero markup for the theme home page
    app(\App\Cms\Hooks\Hooks::class)->addFilter('cms.home.hero', function ($html) {
        $settings = app(Settings::class);

        if (!(bool) $settings->get('enabled', true, 'plugin:hero')) {
            return '';
        }

        $headline = (string) $settings->get('headline', 'YOUR RELIABLE PARTNER', 'plugin:hero');
        $subtext  = (string) $settings->get('subtext', '', 'plugin:hero');

        return view('plugin-hero::hero', compact('headline', 'subtext'))->render();
    });
});
