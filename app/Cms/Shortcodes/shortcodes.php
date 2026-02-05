<?php

use App\Cms\Shortcodes\CoreShortcodes;

// If your shortcode helper functions are defined elsewhere, don't redeclare them here.
// This file exists mainly because composer.json autoload.files expects it.

// Register system shortcodes once
if (class_exists(CoreShortcodes::class) && function_exists('add_shortcode')) {
    CoreShortcodes::register();
}