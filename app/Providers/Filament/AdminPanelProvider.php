<?php

namespace App\Providers\Filament;

use App\Cms\Hooks\HookPoints;
use App\Cms\Hooks\Hooks;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $frontendHomeUrl = url('/');

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('lara-admin')
            ->login()
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Media')->collapsed(),
                NavigationGroup::make()->label('Tags')->collapsed(),
                NavigationGroup::make()->label('Blog Posts')->collapsed(),
                NavigationGroup::make()->label('Static Posts')->collapsed(),
                NavigationGroup::make()->label('Mega Post')->collapsed(),
                NavigationGroup::make()->label('Appearance')->collapsed(),
                NavigationGroup::make()->label('CMS')->collapsed(),
                NavigationGroup::make()->label('SEO')->collapsed(),
                NavigationGroup::make()->label('Tools')->collapsed(),
            ])
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )
            ->pages([
                Dashboard::class,

                // Appearance
                \App\Filament\Pages\Themes::class,
                \App\Filament\Pages\Appearance\Menus::class,
                \App\Filament\Pages\Appearance\Widgets::class,
                \App\Filament\Pages\FooterBuilder::class,

                // CMS
                \App\Filament\Pages\Cms\Plugins::class,
                \App\Filament\Pages\Cms\PluginSettings::class,
                \App\Filament\Pages\Cms\PluginEditor::class,
                \App\Filament\Pages\Cms\Search::class,
                \App\Filament\Pages\Cms\Backups::class,
                \App\Filament\Pages\Cms\MediaSettings::class,

                // Settings
                \App\Filament\Pages\ManageCmsSettings::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName('Siatex CMS')
            ->homeUrl($frontendHomeUrl);

        $panel->renderHook(PanelsRenderHook::TOPBAR_START, function () use ($frontendHomeUrl): string {
            $u = e($frontendHomeUrl);

            return <<<HTML
<div class="flex items-center">
    <a href="{$u}"
       target="_blank"
       rel="noopener noreferrer"
       class="text-xl font-bold tracking-tight"
       style="line-height: 1;">
        Siatex CMS
    </a>
</div>
HTML;
        });

        $panel->renderHook(PanelsRenderHook::HEAD_END, function (): string {
            return <<<HTML
<style>
.fi-topbar .fi-logo,
.fi-topbar .fi-brand {
    display: none !important;
}

.fi-topbar .fi-logo a,
.fi-topbar .fi-brand a,
.fi-topbar a.fi-logo {
    pointer-events: none !important;
}
</style>
HTML;
        });

        $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, function (): string {
            return Blade::render('
        <div class="flex items-center gap-2">
            @livewire("filament.toggle-frontend-admin-bar")
            @livewire("filament.view-shortcodes")
        </div>
    ');
        });

        app(Hooks::class)->doAction(HookPoints::FILAMENT_ADMIN_PANEL, $panel);

        return $panel;
    }
}