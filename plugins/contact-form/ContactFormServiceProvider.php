<?php

namespace Plugins\ContactForm;

use Illuminate\Support\ServiceProvider;

class ContactFormServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // plugin views
        $this->loadViewsFrom(__DIR__ . '/views', 'plugins.contact-form');
    }
}