<?php

namespace Tests\Regression;

use App\Jobs\ImportJiraTicketsJob;
use App\Models\Ticket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JiraJobTest extends IsolatedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('projects', function ($t) {
            $t->id(); $t->string('name'); $t->text('description')->nullable(); $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('status_id'); $t->string('ticket_prefix')->unique(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('project_users', function ($t) { $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('user_id'); $t->string('role'); $t->timestamps(); });
        foreach (['project_statuses', 'ticket_statuses', 'ticket_types', 'ticket_priorities'] as $table) {
            Schema::create($table, function ($t) { $t->id(); $t->string('name'); $t->boolean('is_default'); $t->unsignedBigInteger('project_id')->nullable(); $t->timestamps(); $t->softDeletes(); });
            DB::table($table)->insert(['name' => 'Default', 'is_default' => true]);
        }
        Schema::create('tickets', function ($t) {
            $t->id(); $t->string('name'); $t->text('content'); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('responsible_id')->nullable(); $t->unsignedBigInteger('sprint_id')->nullable(); $t->unsignedBigInteger('epic_id')->nullable();
            $t->unsignedBigInteger('status_id'); $t->unsignedBigInteger('type_id'); $t->unsignedBigInteger('priority_id');
            $t->string('code')->unique(); $t->string('external_source')->nullable()->unique(); $t->integer('order'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('ticket_subscribers', function ($t) { $t->unsignedBigInteger('ticket_id'); $t->unsignedBigInteger('user_id'); });
        DB::table('users')->insert(['id' => 7, 'name' => 'Tester']);
        $this->app['config']->set('system.projects.affectations.roles.can_manage', 'administrator');
    }

    private function issue(): object
    {
        return json_decode(json_encode(['key' => 'ABC-10', 'fields' => ['summary' => 'Imported',
            'description' => ['content' => [['text' => 'Description']]], 'project' => ['key' => 'ABC', 'name' => 'Project']]]));
    }

    public function test_retry_does_not_duplicate_imported_tickets(): void
    {
        $user = $this->actingAs(7, ['Import from Jira', 'Create ticket', 'Create project', 'Update project']);
        $job = new ImportJiraTicketsJob([$this->issue()], $user, 'https://team.atlassian.net');
        $job->handle(); $job->handle();
        $this->assertSame(1, DB::table('tickets')->count());
        $this->assertSame(1, DB::table('projects')->count());
        $this->assertSame('ABC-1', DB::table('tickets')->value('code'));
        $this->assertStringContainsString('Description', DB::table('tickets')->value('content'));
    }

    public function test_import_does_not_write_into_another_users_project(): void
    {
        DB::table('projects')->insert(['name' => 'Private', 'owner_id' => 8, 'status_id' => 1, 'ticket_prefix' => 'ABC']);
        $user = $this->actingAs(7, ['Import from Jira', 'Create ticket', 'Create project', 'Update project']);
        try {
            (new ImportJiraTicketsJob([$this->issue()], $user, 'https://team.atlassian.net'))->handle();
            $this->fail('Unauthorized project import succeeded');
        } catch (AuthorizationException $e) {
            $this->assertSame(0, DB::table('tickets')->count());
        }
    }
}
