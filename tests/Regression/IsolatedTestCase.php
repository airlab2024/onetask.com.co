<?php

namespace Tests\Regression;

use App\Models\User;
use HtmlSanitizer\Sanitizer;
use HtmlSanitizer\SanitizerInterface;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\TestCase;

abstract class IsolatedTestCase extends TestCase
{
    protected Application $app;
    protected string $sandbox;
    protected ?User $actor = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = sys_get_temp_dir() . '/onetask-regression-' . bin2hex(random_bytes(8));
        mkdir($this->sandbox, 0700, true);
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->useStoragePath($this->sandbox);
        $this->app->instance('env', 'testing');
        $this->app->instance('config', new Repository([
            'app' => ['name' => 'ONETASK tests', 'locale' => 'en', 'fallback_locale' => 'en', 'timezone' => 'America/Bogota'],
            'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]],
            'filesystems' => ['default' => 'local', 'disks' => [
                'local' => ['driver' => 'local', 'root' => $this->sandbox . '/local'],
                'public' => ['driver' => 'local', 'root' => $this->sandbox . '/public'],
                'task_files' => ['driver' => 'local', 'root' => $this->sandbox . '/private', 'visibility' => 'private', 'throw' => true],
            ]],
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
            'permission' => require dirname(__DIR__, 2) . '/config/permission.php',
            'view' => ['paths' => [dirname(__DIR__, 2) . '/resources/views'], 'compiled' => $this->sandbox . '/views'],
            'livewire' => ['temporary_file_upload' => ['disk' => 'local', 'directory' => 'livewire-tmp']],
            'forms' => ['default_filesystem_disk' => 'task_files'],
            'filament' => ['auth' => ['guard' => 'web']],
        ]));
        $this->app->instance('request', Request::create('http://localhost'));
        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\ArraySessionHandler(120));
        $this->app->instance('session', $session);
        $this->app['request']->setLaravelSession($session);
        $this->app->instance('livewire', new \Livewire\LivewireManager());
        $this->app['config']->set('livewire.class_namespace', 'App\\Http\\Livewire');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        foreach ([
            \Illuminate\Events\EventServiceProvider::class,
            \Illuminate\Database\DatabaseServiceProvider::class,
            \Illuminate\Filesystem\FilesystemServiceProvider::class,
            \Illuminate\Cache\CacheServiceProvider::class,
            \Illuminate\Translation\TranslationServiceProvider::class,
            \Illuminate\Validation\ValidationServiceProvider::class,
            \Illuminate\Routing\RoutingServiceProvider::class,
            \Illuminate\View\ViewServiceProvider::class,
            \Illuminate\Notifications\NotificationServiceProvider::class,
        ] as $provider) {
            $this->app->register($provider);
        }
        $this->app->bind(SanitizerInterface::class, fn () => Sanitizer::create(require dirname(__DIR__, 2) . '/vendor/filament/support/config/html-sanitizer.php'));
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturnUsing(fn () => $this->actor);
        $guard->shouldReceive('id')->andReturnUsing(fn () => $this->actor?->id);
        $auth = Mockery::mock(AuthManager::class);
        $auth->shouldReceive('guard')->andReturn($guard);
        $auth->shouldReceive('user')->andReturnUsing(fn () => $this->actor);
        $auth->shouldReceive('id')->andReturnUsing(fn () => $this->actor?->id);
        $this->app->instance('auth', $auth);
        $gate = new Gate($this->app, fn () => $this->actor);
        $gate->policy(\App\Models\Task::class, \App\Policies\TaskPolicy::class);
        $gate->policy(\App\Models\Ticket::class, \App\Policies\TicketPolicy::class);
        $gate->policy(\App\Models\Project::class, \App\Policies\ProjectPolicy::class);
        $this->app->instance(GateContract::class, $gate);
        $this->app->instance('filament', new \Filament\FilamentManager());
        $this->app->boot();
        $this->app['router']->get('/tasks/{task}', fn () => '')->name('tasks.show');
        $this->app['router']->get('/login', fn () => '')->name('login');
        $this->app['router']->get('/tasks/{task}/files/{field}', fn () => '')->name('tasks.files');
        $this->app['router']->getRoutes()->refreshNameLookups();
        Notification::fake();
        Schema::create('tasks', function ($table) {
            $table->id(); $table->string('task_short_code')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('eliminado_por')->nullable();
            $table->unsignedBigInteger('asignado_a')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('original_filenames_fotos_ingreso')->nullable();
            foreach (['fotos_ingreso', 'documento', 'remision_ingreso', 'remision_salida', 'remision_ingreso_nombre', 'remision_salida_nombre'] as $field) {
                $table->text($field)->nullable();
            }
            $table->date('cronograma_inicio')->nullable(); $table->date('cronograma_fin')->nullable();
            $table->string('estado')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('users', function ($table) { $table->id(); $table->string('name'); $table->softDeletes(); });
        Schema::create('roles', function ($table) { $table->id(); $table->string('name'); $table->string('guard_name'); });
        Schema::create('model_has_roles', function ($table) { $table->unsignedBigInteger('role_id'); $table->unsignedBigInteger('model_id'); $table->string('model_type'); });
    }

    protected function actingAs(int $id, array $permissions = [], array $roles = []): User
    {
        $this->actor = Mockery::mock(User::class)->makePartial();
        $this->actor->forceFill(['id' => $id, 'name' => 'Tester']);
        $this->actor->shouldReceive('hasRole')->andReturnUsing(fn ($role) => in_array($role, $roles, true));
        $this->actor->shouldReceive('can')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        return $this->actor;
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        Mockery::close();
        Facade::clearResolvedInstances();
        $this->app->flush();
        // Only remove the verified, uniquely created test directory.
        $actual = realpath($this->sandbox);
        $temp = realpath(sys_get_temp_dir());
        if ($actual && $temp && str_starts_with(str_replace('\\', '/', $actual), rtrim(str_replace('\\', '/', $temp), '/') . '/onetask-regression-')) {
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($actual);
        }
        parent::tearDown();
    }
}
