<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Http\Livewire\ProfilePersonalInfo;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class OnetaskPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $social = FilamentSocialitePlugin::make()->slug('onetask')
            ->registration(fn () => (bool) config('filament-socialite.registration'))
            ->providers(collect(config('filament-socialite.providers', []))
                ->map(fn ($options, $name) => Provider::make($name)->label($options['label'])->icon($options['icon'])
                    ->hidden(fn () => !\App\Support\ExternalLoginConfiguration::social($name)))->values()->all());

        return $panel->default()->id('onetask')->path('')
            ->login(Login::class)->passwordReset()->emailVerification()
            ->registration(config('filament-breezy.enable_registration') ? \Filament\Pages\Auth\Register::class : null)
            ->authGuard('web')->brandName(fn () => config('app.name'))->font('DM Sans')
            ->brandLogo(fn () => config('app.logo'))->favicon(fn () => config('app.logo'))
            ->colors(['primary' => Color::Blue])->maxContentWidth('full')->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([\App\Filament\Pages\Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->databaseNotifications()->databaseNotificationsPolling('30s')->viteTheme('resources/css/filament.scss')
            ->plugins([
                BreezyCore::make()->myProfile()->enableTwoFactorAuthentication()
                    ->myProfileComponents(['personal_info' => ProfilePersonalInfo::class]),
                $social,
            ])
            ->middleware([
                EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
                AuthenticateSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class,
                SubstituteBindings::class, DispatchServingFilamentEvent::class,
                \App\Http\Middleware\LocaleMiddleware::class, \App\Http\Middleware\ExternalLoginEnabled::class,
            ])->authMiddleware([Authenticate::class]);
    }
}
