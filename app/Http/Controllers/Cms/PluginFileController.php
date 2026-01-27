<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Core\SafeMode;
use App\Cms\Plugins\PluginFileManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PluginFileController
{
    public function show(Request $request, string $slug, string $path, PluginFileManager $fm): Response
    {
        $user = Auth::user();
        abort_unless($user, 403);

        // If spatie roles exist
        if (method_exists($user, 'hasRole')) {
            abort_unless($user->hasRole('super-admin'), 403);
        } else {
            abort_unless((int) $user->id === 1, 403);
        }

        // If safe mode enabled, still allow preview (read-only), but you can block if you want.
        $safe = app(SafeMode::class);
        if ($safe->isEnabled($request)) {
            // preview allowed
        }

        $abs = $fm->absolutePath($slug, $path);
        abort_unless(is_file($abs), 404);

        $mime = $fm->mime($slug, $path);

        return response()->file($abs, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}