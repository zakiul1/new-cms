<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\ContentRouterController;

Route::get('/', function () {
    return view('home'); // ThemeManager adds theme views as a location, so this resolves to themes/{active}/views/home.blade.php
});

Route::get('/{slug}', [ContentRouterController::class, 'show'])
    ->where('slug', '.*');