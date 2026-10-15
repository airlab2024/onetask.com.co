<?php

namespace Tests\Feature;

use App\Providers\Filament\OnetaskPanelProvider;
use Filament\Panel;
use Tests\TestCase;

class PanelCompatibilityTest extends TestCase
{
    public function test_login_loads_javascript_from_the_installed_livewire_version(): void
    {
        $this->assertFileDoesNotExist(public_path('vendor/livewire/manifest.json'),
            'Published Livewire assets can retain a manifest from an incompatible major version.');
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertSame(1, preg_match('~<script[^>]+src="([^"]+)"[^>]+data-update-uri="([^"]+)"~s', $html, $match));
        $script = html_entity_decode($match[1]);
        $this->assertStringNotContainsString('/vendor/livewire/', $script);
        $path = parse_url($script, PHP_URL_PATH);
        $response = $this->get($path.'?'.parse_url($script, PHP_URL_QUERY))->assertOk();
        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type'));
        $expected = base_path('vendor/livewire/livewire/dist/'.basename($path));
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(realpath($expected), $response->baseResponse->getFile()->getRealPath());
    }

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
