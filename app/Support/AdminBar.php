<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class AdminBar
{
    /**
     * Show the admin bar only for users who can access the Filament panel.
     * Works with Filament v5 where canAccessPanel expects a Panel object.
     */
    public static function canShow(string $panelId = 'admin'): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // ✅ If you use roles (Spatie), allow admin + super_admin quickly
        if (method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // ✅ Filament check (expects Panel object, not string)
        if (method_exists($user, 'canAccessPanel')) {
            $panel = self::resolvePanel($panelId);

            if ($panel instanceof Panel) {
                return (bool) $user->canAccessPanel($panel);
            }
        }

        return false;
    }

    private static function resolvePanel(string $panelId): ?Panel
    {
        // Try the normal id first (usually "admin")
        $panel = Filament::getPanel($panelId, false);
        if ($panel instanceof Panel) {
            return $panel;
        }

        // Some projects confuse panel id vs path; try common alternative
        $panel = Filament::getPanel('lara-admin', false);
        if ($panel instanceof Panel) {
            return $panel;
        }

        // Fallback: first registered panel (if any)
        $panels = Filament::getPanels();
        foreach ($panels as $p) {
            if ($p instanceof Panel) {
                return $p;
            }
        }

        return null;
    }
}