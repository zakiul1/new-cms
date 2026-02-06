@php
    $panelId = 'admin';

    // show only for logged-in users who can access filament
    $showAdminBar = auth()->check() && auth()->user()?->can('access_filament');

    // Filament admin dashboard URL (panel id = admin)
    $dashboardUrl = route("filament.{$panelId}.pages.dashboard");
@endphp

@if ($showAdminBar)
    <div id="cms-admin-bar"
        style="position:fixed;top:0;left:0;right:0;z-index:99999;height:32px;
                background:#1d2327;color:#fff;font:13px/32px system-ui,-apple-system,Segoe UI,Roboto,sans-serif;">
        <div style="max-width:1200px;margin:0 auto;padding:0 12px;display:flex;gap:14px;align-items:center;">
            <a href="{{ $dashboardUrl }}" style="color:#fff;text-decoration:none;font-weight:600;">
                Admin
            </a>

            @if (!empty($adminEditUrl))
                <a href="{{ $adminEditUrl }}" style="color:#72aee6;text-decoration:none;">
                    Edit
                </a>
            @endif

            <div style="margin-left:auto;display:flex;gap:14px;align-items:center;">
                <span style="opacity:.85;">
                    {{ auth()->user()->name ?? auth()->user()->email }}
                </span>

                <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" style="background:transparent;border:0;color:#fff;cursor:pointer;padding:0;">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- push website down like WP --}}
    <div style="height:32px;"></div>
@endif
