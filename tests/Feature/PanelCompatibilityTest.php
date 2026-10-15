<?php

namespace Tests\Feature;

use App\Providers\Filament\OnetaskPanelProvider;
use Filament\Panel;
use Tests\TestCase;

class PanelCompatibilityTest extends TestCase
{
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
