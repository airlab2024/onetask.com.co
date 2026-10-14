<?php

namespace App\Providers;

use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Applies to application forms and authentication packages alike.
        $this->app['validator']->resolver(fn ($translator, $data, $rules, $messages, $attributes) =>
            new \App\Support\StrictValidator($translator, $data, $rules, $messages, $attributes));

        // Configure application
        $this->configureApp();
        \Jeffgreco13\FilamentBreezy\Models\BreezySession::saved(fn ($session) => \App\Support\BreezyEnrollmentSync::updateLegacyColumns($session));
        \Jeffgreco13\FilamentBreezy\Models\BreezySession::deleted(fn ($session) => \App\Support\BreezyEnrollmentSync::updateLegacyColumns($session, true));

        // Panel configuration lives in OnetaskPanelProvider. Publish custom assets
        // through the supported Filament 3 asset registry.
        try {
            \Filament\Support\Facades\FilamentAsset::register([
                \Filament\Support\Assets\Js::make('onetask', app(Vite::class)->asset('resources/js/filament.js'))->module(),
                \Filament\Support\Assets\Css::make('tippy', 'https://unpkg.com/tippy.js@6/dist/tippy.css'),
                \Filament\Support\Assets\Css::make('onetask-custom', asset('css/filament-custom.css')),
            ]);
        } catch (\Illuminate\Foundation\ViteManifestNotFoundException $e) {
            // Assets are built before the candidate is exposed to HTTP.
        }

        // Force HTTPS over HTTP
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }


    }

    private function configureApp(): void
    {
        try {
            $settings = app(GeneralSettings::class);
            Config::set('app.locale', $settings->site_language ?? config('app.fallback_locale'));
            Config::set('app.name', $settings->site_name ?? config('app.name'));
            Config::set('filament.brand', $settings->site_name ?? config('app.name'));
            Config::set(
                'app.logo',
                $settings->site_logo ? asset('storage/' . $settings->site_logo) : asset('favicon.ico')
            );
            Config::set('filament-breezy.enable_registration', $settings->enable_registration ?? false);
            Config::set('filament-socialite.registration', $settings->enable_registration ?? false);
            Config::set('filament-socialite.enabled', $settings->enable_social_login ?? false);
            Config::set('system.login_form.is_enabled', $settings->enable_login_form ?? false);
            Config::set('services.oidc.is_enabled', $settings->enable_oidc_login ?? false);
        } catch (QueryException $e) {
            // Error: No database configured yet
        }
    }
}
