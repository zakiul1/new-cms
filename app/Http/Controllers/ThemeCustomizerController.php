<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeCustomizerController extends Controller
{
    public function index(Request $request)
    {
        $theme = (string) $request->query('theme', 'default');

        return view('cms.customizer.app', [
            'theme' => $theme,
        ]);
    }
}