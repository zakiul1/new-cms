<?php

namespace App\Cms\Core;

use Illuminate\Http\Request;

class SafeMode
{
    private const SESSION_KEY = 'cms_safe_mode';

    /**
     * Enable safe mode by visiting any page with: ?cms_safe_mode=1
     * Safe for CLI (no session available).
     */
    public function maybeEnableFromRequest(?Request $request): void
    {
        if (!$request) {
            return;
        }

        // If session isn't available (CLI / early boot), do nothing.
        if (!method_exists($request, 'hasSession') || !$request->hasSession()) {
            return;
        }

        if ($request->boolean('cms_safe_mode')) {
            $request->session()->put(self::SESSION_KEY, true);
        }
    }

    /**
     * Safe for CLI (returns false when no session).
     */
    public function isEnabled(?Request $request): bool
    {
        if (!$request) {
            return false;
        }

        if (!method_exists($request, 'hasSession') || !$request->hasSession()) {
            return false;
        }

        return (bool) $request->session()->get(self::SESSION_KEY, false);
    }

    public function disable(?Request $request): void
    {
        if (!$request) {
            return;
        }

        if (!method_exists($request, 'hasSession') || !$request->hasSession()) {
            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }
}