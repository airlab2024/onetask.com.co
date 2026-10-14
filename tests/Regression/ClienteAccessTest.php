<?php

namespace Tests\Regression;

use App\Models\Cliente;
use App\Models\Task;
use App\Policies\ClientePolicy;
use Illuminate\Support\Facades\Schema;

class ClienteAccessTest extends IsolatedTestCase
{
    public function test_clients_require_permission_and_cannot_delete_order_history(): void
    {
        Schema::create('clientes', function ($table) { $table->id(); $table->string('nombre'); $table->timestamps(); });
        Schema::table('tasks', fn ($table) => $table->unsignedBigInteger('cliente_id')->nullable());
        $policy = new ClientePolicy();
        $client = Cliente::create(['nombre' => 'Test']);
        $this->assertFalse($policy->viewAny($this->actingAs(7)));
        $this->assertTrue($policy->viewAny($this->actingAs(7, ['Cliente resource'])));
        $this->assertFalse($policy->delete($this->actor, $client));
        $admin = $this->actingAs(1, [], ['SUPER ADMINISTRADOR']);
        $this->assertTrue($policy->delete($admin, $client));
        Task::withoutEvents(fn () => Task::forceCreate(['cliente_id' => $client->id, 'deleted_at' => now()]));
        $this->assertFalse($policy->delete($admin, $client));
        $this->assertFalse($policy->deleteAny($admin));
    }
}
