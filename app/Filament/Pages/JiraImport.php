<?php

namespace App\Filament\Pages;

use App\Helpers\JiraHelper;
use App\Jobs\ImportJiraTicketsJob;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use App\Support\JiraEndpoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class JiraImport extends Page implements HasForms
{
    use InteractsWithForms, JiraHelper;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';

    protected static string $view = 'filament.pages.jira-import';

    protected static ?string $slug = 'jira-import';

    protected static ?int $navigationSort = 2;

    protected $listeners = [
        'updateJiraProjects',
        'updateJiraTickets'
    ];

    public $host;
    public $username;
    public $token;
    public bool $loadingProjects = false;
    public $projects = [];
    public $selected_projects;
    public bool $loadingTickets = false;
    public $tickets = [];
    public $selected_tickets;
    public $data = [];
    public $ticketsDataApi;

    public function mount(): void
    {
        $this->authorizeImport();
        $this->form->fill();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('Import from Jira');
    }

    private function authorizeImport(): void
    {
        abort_unless(auth()->user()?->can('Import from Jira'), 403);
    }

    private function selectionCacheKey(): string
    {
        return 'jira-selection:' . auth()->id() . ':' . hash('sha256', session()->getId());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Use this section to login into your jira account and import tickets to this application');
    }

    public static function getNavigationLabel(): string
    {
        return __('Jira import');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    protected function getFormSchema(): array
    {
        return [
            Card::make()
                ->schema([
                    Wizard::make([
                        Wizard\Step::make(__('Jira login'))
                            ->schema([
                                Placeholder::make('info')
                                    ->extraAttributes([
                                        'class' => 'bg-primary-500 rounded-lg border border-primary-600 text-white font-medium text-sm py-3 px-4'
                                    ])
                                    ->disableLabel()
                                    ->content(__('Important: Your jira credentials are only used to communicate with jira REST API, and will not be stored in this application')),

                                Grid::make()
                                    ->schema([
                                        TextInput::make('host')
                                            ->label(__('Host'))
                                            ->helperText(__('The url used to access your jira account'))
                                            ->required(),

                                        TextInput::make('username')
                                            ->label(__('Username'))
                                            ->helperText(__('Your jira account username'))
                                            ->required(),

                                        TextInput::make('token')
                                            ->label(__('API Token'))
                                            ->helperText(__('Your jira account API Token'))
                                            ->password()
                                            ->required(),
                                    ]),
                            ])
                            ->afterValidation(function () {
                                $this->loadingProjects = true;
                                $this->dispatch('updateJiraProjects');
                            }),

                        Wizard\Step::make(__('Jira projects'))
                            ->schema([
                                Placeholder::make('hint')
                                    ->extraAttributes([
                                        'class' => 'bg-primary-500 rounded-lg border border-primary-600 text-white font-medium text-sm py-3 px-4'
                                    ])
                                    ->disableLabel()
                                    ->visible(fn() => !$this->loadingProjects && $this->projects)
                                    ->content(__('Choose your jira projects to import')),

                                Placeholder::make('loading')
                                    ->extraAttributes([
                                        'class' => 'bg-warning-500 rounded-lg border border-warning-600 text-white font-medium text-sm py-3 px-4'
                                    ])
                                    ->disableLabel()
                                    ->visible(fn() => $this->loadingProjects)
                                    ->content(__('Loading projects, please wait...')),

                                Placeholder::make('info')
                                    ->extraAttributes([
                                        'class' => 'bg-danger-500 rounded-lg border border-danger-600 text-white font-medium text-sm py-3 px-4'
                                    ])
                                    ->disableLabel()
                                    ->visible(fn() => !$this->loadingProjects && !$this->projects)
                                    ->content(__('Your jira credentials are incorrect, please go to previous step and re-enter your jira credentials')),

                                CheckboxList::make('selected_projects')
                                    ->label(__('Jira projects'))
                                    ->required()
                                    ->visible(fn() => $this->projects)
                                    ->options(function () {
                                        $list = [];
                                        if ($this->projects) {
                                            foreach ($this->projects as $project) {
                                                $project = (object) $project;
                                                $list[$project->key] = new HtmlString(
                                                    "<div class='w-full flex flex-col gap-1'>"
                                                    . "<div class='w-full flex items-center gap-1'>"
                                                    . "<span class='font-medium text-gray-700 text-base'>" . e($project->name) . "</span>"
                                                    . "<div class='text-gray-700 text-xs font-light'><span class='font-medium uppercase'>/</span> " . e($project->key) . "</div>"
                                                    . "</div>"
                                                    . "</div>"
                                                );
                                            }
                                        }
                                        return $list;
                                    }),

                            ])
                            ->afterValidation(function () {
                                $this->loadingTickets = true;
                                $this->dispatch('updateJiraTickets');
                            }),

                        Wizard\Step::make(__('Jira tickets'))
                            ->schema(function () {
                                $fields = [];

                                $fields[] = Placeholder::make('hint')
                                    ->extraAttributes([
                                        'class' => 'bg-primary-500 rounded-lg border border-primary-600 text-white font-medium text-sm py-3 px-4'
                                    ])
                                    ->disableLabel()
                                    ->visible(fn() => !$this->loadingTickets && $this->tickets)
                                    ->content(__('Choose your jira projects to import'));

                                $fields[] = Placeholder::make('loading')
                                    ->extraAttributes([
                                        'class' => 'bg-warning-500 rounded-lg border border-warning-600 text-white font-medium text-sm py-3 px-4'
                                    ])
                                    ->disableLabel()
                                    ->visible(fn() => $this->loadingTickets)
                                    ->content(__('Loading tickets, please wait...'));

                                if (!$this->loadingTickets) {
                                    if ($this->tickets) {
                                        foreach ($this->tickets as $projectKey => $ticket) {
                                            if ($ticket['total'] > 0) {
                                                $fields[] = Placeholder::make('tickets_' . Str::slug($projectKey))
                                                    ->label(__('Tickets for the project:') . ' ' . $projectKey)
                                                    ->extraAttributes([
                                                        'style' => 'margin-bottom: -15px;'
                                                    ])
                                                    ->content('');

                                                foreach ($ticket['issues'] as $issue) {
                                                    $fields[] = Checkbox::make('data.' . Str::slug($projectKey) . '_' . Str::slug($issue['code']))
                                                        ->label(function () use ($issue) {
                                                            return new HtmlString(
                                                                "<div class='w-full flex flex-col gap-1'>"
                                                                . "<div class='w-full flex items-center gap-1'>"
                                                                . "<div class='text-gray-700 text-xs font-light'><span class='font-medium uppercase'>" . e($issue['code']) . "</span> " . e($issue['name']) . "</div>"
                                                                . "</div>"
                                                                . "</div>"
                                                            );
                                                        });
                                                }
                                            } else {
                                                $fields[] = Placeholder::make('no_tickets_' . Str::slug($projectKey))
                                                    ->label(__('Tickets for the project:') . ' ' . $projectKey)
                                                    ->content(__('No tickets found!'));
                                            }
                                        }
                                    } else {
                                        $fields[] = Placeholder::make('info')
                                            ->extraAttributes([
                                                'class' => 'bg-warning-500 rounded-lg border border-warning-600 text-white font-medium text-sm py-3 px-4'
                                            ])
                                            ->disableLabel()
                                            ->visible(fn() => !$this->projects)
                                            ->content(__('No tickets found!'));
                                    }
                                }
                                return $fields;
                            }),
                    ])
                        ->submitAction(new HtmlString("<button type='submit' class='px-3 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded'>" . __('Import') . "</button>")),
                ]),
        ];
    }

    public function import(): void
    {
        $this->authorizeImport();
        $host = JiraEndpoint::normalize((string) $this->host);
        $selection = Cache::get($this->selectionCacheKey());
        if (!$selection || ($selection['host'] ?? '') !== $host) {
            throw ValidationException::withMessages(['data' => 'La selección expiró. Vuelve a cargar las incidencias.']);
        }
        $selected = array_keys(array_filter((array) $this->data, fn ($value) => in_array($value, [true, 1, '1'], true)));
        if (!$selected || count($selected) > config('jira.max_selected', 50)) {
            throw ValidationException::withMessages(['data' => 'Selecciona entre 1 y 50 incidencias por importación.']);
        }
        $tickets = [];
        foreach ($selected as $item) {
            $key = $selection['issues'][$item] ?? null;
            if (!$key) throw ValidationException::withMessages(['data' => 'La incidencia no pertenece a la selección.']);
            $ticket = $this->getJiraTicketDetails($host, $this->username, $this->token, $key);
            if (!$ticket || !in_array($ticket->fields->project->key ?? '', $selection['projects'], true)) {
                throw ValidationException::withMessages(['data' => 'No se pudo validar la incidencia solicitada.']);
            }
            $tickets[] = $ticket;
        }
        dispatch(new ImportJiraTicketsJob($tickets, auth()->user(), $host));
        Cache::forget($this->selectionCacheKey());
        \App\Support\UiNotification::send('success', __('The importation job is started, when finished you will be notified'), true);
        $this->redirect(route('filament.onetask.pages.jira-import'));
    }

    public function updateJiraProjects(): void
    {
        $this->authorizeImport();
        $this->loadingProjects = true;
        try {
            $this->host = JiraEndpoint::normalize((string) $this->host);
            $projects = $this->getJiraProjects($this->connectToJira($this->host, $this->username, $this->token));
            $this->projects = array_map(fn ($p) => ['key' => $p->key, 'name' => $p->name], $projects ?? []);
            $this->tickets = []; $this->data = []; $this->selected_projects = [];
            Cache::put($this->selectionCacheKey(), ['host' => $this->host,
                'projects' => array_column($this->projects, 'key'), 'issues' => []], 900);
        } finally {
            $this->loadingProjects = false;
        }
    }

    public function updateJiraTickets(): void
    {
        $this->authorizeImport();
        $host = JiraEndpoint::normalize((string) $this->host);
        $known = Cache::get($this->selectionCacheKey());
        $selected = (array) $this->selected_projects;
        if (!$known || $known['host'] !== $host || !$selected || array_diff($selected, $known['available_projects'] ?? $known['projects'])) {
            throw ValidationException::withMessages(['selected_projects' => 'Selecciona proyectos de la consulta actual.']);
        }
        $this->loadingTickets = true;
        try {
            $this->ticketsDataApi = []; $this->data = [];
            $this->tickets = $this->getJiraTicketsByProject($this->connectToJira($host, $this->username, $this->token), $selected) ?? [];
            foreach ($this->tickets as $projectKey => &$project) {
                foreach ($project['issues'] as &$issue) {
                    $index = Str::slug($projectKey) . '_' . Str::slug($issue['code']);
                    $this->ticketsDataApi[$index] = $issue['code'];
                    unset($issue['data']);
                }
                unset($issue);
            }
            unset($project);
            Cache::put($this->selectionCacheKey(), ['host' => $host, 'projects' => $selected,
                'issues' => $this->ticketsDataApi, 'available_projects' => $known['available_projects'] ?? $known['projects']], 900);
        } finally {
            $this->loadingTickets = false;
        }
    }
}
