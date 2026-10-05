<?php

namespace Tests\Regression;

use App\Filament\Pages\Kanban;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Http\Controllers\Auth\OidcAuthController;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccessRegressionTest extends IsolatedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('projects', function ($table) {
            $table->id(); $table->string('name'); $table->unsignedBigInteger('owner_id');
            $table->string('ticket_prefix')->nullable(); $table->string('status_type')->default('default');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('project_users', function ($table) { $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('user_id'); $table->string('role'); });
        Schema::create('tickets', function ($table) {
            $table->id(); $table->string('name'); $table->text('content')->nullable();
            $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('responsible_id')->nullable(); $table->unsignedBigInteger('status_id');
            $table->unsignedBigInteger('sprint_id')->nullable(); $table->unsignedBigInteger('epic_id')->nullable();
            $table->integer('order')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('ticket_statuses', function ($table) { $table->id(); $table->string('name'); $table->unsignedBigInteger('project_id')->nullable(); $table->timestamps(); $table->softDeletes(); });
        Schema::create('ticket_comments', function ($table) { $table->id(); $table->text('content'); $table->unsignedBigInteger('ticket_id'); $table->unsignedBigInteger('user_id'); $table->timestamps(); $table->softDeletes(); });
        Schema::create('ticket_activities', function ($table) { $table->id(); $table->unsignedBigInteger('ticket_id'); $table->unsignedBigInteger('old_status_id'); $table->unsignedBigInteger('new_status_id'); $table->unsignedBigInteger('user_id'); $table->timestamps(); });
    }

    private function ticket(int $owner = 7): Ticket
    {
        $project = Project::create(['name' => 'Project', 'owner_id' => 9, 'ticket_prefix' => 'TEST']);
        $status = TicketStatus::withoutEvents(fn () => TicketStatus::create(['name' => 'New']));
        return Ticket::withoutEvents(fn () => Ticket::create(['name' => 'Ticket', 'project_id' => $project->id, 'owner_id' => $owner, 'status_id' => $status->id]));
    }

    public function test_board_cannot_update_a_ticket_from_another_project(): void
    {
        $this->actingAs(7, ['Update ticket']);
        $own = $this->ticket(); $other = $this->ticket();
        $board = new Kanban(); $board->project = $own->project;
        $this->expectException(ModelNotFoundException::class);
        $board->recordUpdated($other->id, 0, $own->status_id);
    }

    public function test_board_rejects_an_update_without_permission(): void
    {
        $this->actingAs(7);
        $ticket = $this->ticket(); $board = new Kanban(); $board->project = $ticket->project;
        $this->expectException(AuthorizationException::class);
        $board->recordUpdated($ticket->id, 0, $ticket->status_id);
    }

    public function test_board_rejects_status_from_another_project(): void
    {
        $this->actingAs(7, ['Update ticket']);
        $ticket = $this->ticket(); $board = new Kanban(); $board->project = $ticket->project;
        $status = TicketStatus::withoutEvents(fn () => TicketStatus::create(['name' => 'Private', 'project_id' => 999]));
        try {
            $board->recordUpdated($ticket->id, 0, $status->id);
            $this->fail('Unrelated status was accepted');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertNotSame($status->id, $ticket->fresh()->status_id);
        }
    }

    public function test_comment_from_another_ticket_cannot_be_deleted(): void
    {
        $this->actingAs(7, ['View ticket']);
        $own = $this->ticket(); $other = $this->ticket();
        $comment = TicketComment::withoutEvents(fn () => TicketComment::create(['ticket_id' => $other->id, 'user_id' => 7, 'content' => 'Other ticket']));
        $page = new ViewTicket(); $page->record = $own;
        $this->expectException(ModelNotFoundException::class);
        $page->doDeleteComment($comment->id);
    }

    public function test_another_authors_comment_cannot_be_deleted_by_a_reader(): void
    {
        $this->actingAs(7, ['View ticket']);
        $ticket = $this->ticket();
        $comment = TicketComment::withoutEvents(fn () => TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => 8, 'content' => 'Other author']));
        $page = new ViewTicket(); $page->record = $ticket;
        try {
            $page->doDeleteComment($comment->id);
            $this->fail('Another author comment was deleted');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertNotNull($comment->fresh());
        }
    }

    public function test_oidc_rejects_wrong_state_before_contacting_the_provider(): void
    {
        $this->app['config']->set('services.oidc', [
            'is_enabled' => true, 'client_id' => 'test', 'client_secret' => 'test',
            'redirect_uri' => 'https://localhost/oidc/callback', 'url_authorize' => 'https://example.invalid/auth',
            'url_access_token' => 'https://example.invalid/token', 'url_resource_owner_details' => 'https://example.invalid/user', 'scope' => 'openid email',
        ]);
        $session = $this->app['session']; $session->put('oidc_state', 'expected');
        $request = Request::create('/', 'GET', ['state' => 'wrong', 'code' => 'unused']);
        $request->setLaravelSession($session);
        try {
            (new OidcAuthController())->callback($request);
            $this->fail('Invalid OIDC state was accepted');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertNull($session->get('oidc_state'));
        }
    }
}
