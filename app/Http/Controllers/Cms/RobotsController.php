<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;

class RobotsController extends Controller
{
    public function show()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /livewire',
            'Sitemap: ' . route('cms.sitemap'),
        ];

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain');
    }
}