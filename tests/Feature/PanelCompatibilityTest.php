<?php

namespace Tests\Feature;

use App\Providers\Filament\OnetaskPanelProvider;
use Filament\Panel;
use Tests\TestCase;

class PanelCompatibilityTest extends TestCase
{
    public function test_unconfigured_external_login_returns_404(): void
    {
        config(['services.oidc.is_enabled' => true, 'services.oidc.client_id' => null,
            'services.oidc.client_secret' => null, 'filament-socialite.enabled' => true,
            'services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->get('/oidc/redirect')->assertNotFound();
        $this->get('/onetask/oauth/google')->assertNotFound();
    }

    public function test_registration_action_rechecks_disabled_setting(): void
    {
        config(['filament-breezy.enable_registration' => false]);
        try {
            (new \App\Filament\Auth\Register())->register();
            $this->fail('Disabled registration accepted a stale component action.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_disabled_registration_has_no_registration_action(): void
    {
        config(['filament-breezy.enable_registration' => false]);
        $panel = (new OnetaskPanelProvider($this->app))->panel(Panel::make());
        $this->assertFalse($panel->hasRegistration());
    }

    public function test_board_links_resolve_with_project_parameter(): void
    {
        $this->assertStringEndsWith('/kanban/123', route('filament.onetask.pages.kanban.{project}', ['project' => 123]));
        $this->assertStringEndsWith('/scrum/123', route('filament.onetask.pages.scrum.{project}', ['project' => 123]));
    }

    public function test_guest_cannot_access_orders_or_downloads(): void
    {
        $this->get('/tasks')->assertRedirect('/login');
        $this->get('/tasks/123/files/fotos_ingreso?path=photos/example.png')->assertRedirect('/login');
    }
}
