<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            ->maxContentWidth('full')

            ->colors([
                'primary' => Color::Amber,
            ])

            // ✅ Sidebar group order
            ->navigationGroups([
                NavigationGroup::make()->label('Appearance')->collapsed(),
                NavigationGroup::make()->label('Media')->collapsed(),

                NavigationGroup::make()->label('CMS')->collapsed(),
                NavigationGroup::make()->label('SEO')->collapsed(),
                NavigationGroup::make()->label('Tools')->collapsed(),
            ])

            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )

            ->pages([
                Dashboard::class,

                // Appearance
                \App\Filament\Pages\Themes::class,
                \App\Filament\Pages\Appearance\Menus::class,
                \App\Filament\Pages\Appearance\Widgets::class,

                // Media (✅ add your Media page/resource if it's a Page)
                // \App\Filament\Pages\Media\MediaLibrary::class,

                // CMS
                \App\Filament\Pages\Cms\Plugins::class,
                \App\Filament\Pages\Cms\PluginSettings::class,
                \App\Filament\Pages\Cms\PluginEditor::class,
                \App\Filament\Pages\Cms\Search::class,
                \App\Filament\Pages\Cms\Backups::class,

                // ✅ CMS Settings page (you forgot to register it)
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
            ]);
    }
}