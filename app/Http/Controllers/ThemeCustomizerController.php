<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeCustomizerController extends Controller
{
    public function redirect(Request $request)
    {
        $theme = (string) $request->query('theme', 'default');

        return redirect()->route('cms.customizer', ['theme' => $theme]);
    }

    public function index(Request $request)
    {
        $theme = (string) $request->query('theme', 'default');

        return view('cms.customizer.app', [
            'theme' => $theme,
        ]);
    }
}