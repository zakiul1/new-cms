<?php

namespace App\Cms\Menus;

use App\Models\MenuItem;
use Illuminate\Contracts\Auth\Authenticatable;

class MenuVisibility
{
    public function shouldShow(MenuItem $item, ?Authenticatable $user): bool
    {
        $vis = is_array($item->visibility) ? $item->visibility : [];

        $authRule = $vis['auth'] ?? 'any'; // any|guest|auth

        if ($authRule === 'guest' && $user) {
            return false;
        }
        if ($authRule === 'auth' && !$user) {
            return false;
        }

        $roles = $vis['roles'] ?? [];
        if (is_array($roles) && $roles !== []) {
            if (!$user) {
                return false;
            }

            // spatie/permission
            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole($roles);
            }
        }

        return true;
    }
}