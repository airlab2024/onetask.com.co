<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\ProjectUser;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportJiraTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $tickets;
    private $user;
    private string $source = '';
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($tickets, $user, string $source = '')
    {
        $this->tickets = $tickets;
        $this->user = $user;
        $this->source = $source;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $gate = \Illuminate\Support\Facades\Gate::forUser($this->user);
        abort_unless($this->user->can('Import from Jira'), 403);
        $gate->authorize('create', Ticket::class);
        $source = \App\Support\JiraEndpoint::normalize($this->source);
        foreach ((array) $this->tickets as $ticket) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($ticket, $gate, $source) {
                $key = \App\Support\JiraEndpoint::issueKey((string) ($ticket->key ?? ''));
                $reference = hash('sha256', $source . $key);
                if (Ticket::withTrashed()->where('external_source', $reference)->exists()) return;
                $details = $ticket->fields->project;
                $projectKey = \App\Support\JiraEndpoint::projectKey((string) $details->key);
                $project = Project::where('ticket_prefix', $projectKey)->lockForUpdate()->first();
                if ($project) {
                    $gate->authorize('update', $project);
                } else {
                    $gate->authorize('create', Project::class);
                    $projectStatus = ProjectStatus::where('is_default', true)->firstOrFail();
                    $project = Project::create([
                        'name' => $details->name, 'description' => __('Project imported from Jira, project key:') . $projectKey,
                        'status_id' => $projectStatus->id, 'owner_id' => $this->user->id, 'ticket_prefix' => $projectKey,
                    ]);
                    ProjectUser::firstOrCreate(['project_id' => $project->id, 'user_id' => $this->user->id],
                        ['role' => config('system.projects.affectations.roles.can_manage')]);
                }
                $status = TicketStatus::where('is_default', true)->whereNull('project_id')->firstOrFail();
                Ticket::create([
                    'name' => $ticket->fields->summary,
                    'content' => \App\Support\JiraEndpoint::description($ticket->fields->description ?? null),
                    'owner_id' => $this->user->id, 'status_id' => $status->id, 'project_id' => $project->id,
                    'type_id' => TicketType::where('is_default', true)->firstOrFail()->id,
                    'priority_id' => TicketPriority::where('is_default', true)->firstOrFail()->id,
                    'external_source' => $reference,
                ]);
            }, 3);
        }
        FilamentNotification::make()->title(__('Jira importation'))->icon('heroicon-o-cloud-download')
            ->body(__('Jira tickets successfully imported'))->sendToDatabase($this->user);
    }
}
