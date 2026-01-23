<?php

use App\Cms\Hooks\HookPoints;

cms_hooks()->addAction(HookPoints::CMS_BOOTED, function () {
    cms_assets()->enqueueScript('hello-plugin', '/plugins/hello/hello.js', ['defer' => 'defer']);
});