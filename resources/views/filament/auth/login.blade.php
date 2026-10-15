<x-filament-panels::page.simple>
    @if (session()->has('oidc_error'))
        <p role="alert">{{ __('OIDC Connect error') }}: {{ __('Invalid account!') }}</p>
    @endif
    @if (config('system.login_form.is_enabled'))
        @if (filament()->hasRegistration())
            <x-slot name="subheading">{{ $this->registerAction }}</x-slot>
        @endif
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}
        <x-filament-panels::form id="form" wire:submit="authenticate">
            {{ $this->form }}
            <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
        </x-filament-panels::form>
    @endif
    @if (\App\Support\ExternalLoginConfiguration::oidc())
        <x-filament::button tag="a" :href="route('oidc.redirect')" icon="heroicon-o-arrow-right-on-rectangle">
            {{ __('OIDC Connect') }}
        </x-filament::button>
    @endif
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
