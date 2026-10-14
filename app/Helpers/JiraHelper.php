<?php

namespace App\Helpers;

use App\Support\JiraEndpoint;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait JiraHelper
{
    public function connectToJira($host, $username, $token): Client
    {
        return new Client([
            'base_uri' => JiraEndpoint::normalize((string) $host),
            'headers' => ['Accept' => 'application/json'],
            'auth' => [$username, $token],
            'allow_redirects' => false,
            'timeout' => 20,
            'connect_timeout' => 5,
        ]);
    }

    public function getJiraProjects(Client $client): ?array
    {
        try {
            $projects = []; $start = 0;
            for ($page = 0; $page < config('jira.max_pages', 100); $page++) {
                $data = json_decode((string) $client->get('rest/api/3/project/search', [
                    'query' => ['startAt' => $start, 'maxResults' => 50],
                ])->getBody(), false, 512, JSON_THROW_ON_ERROR);
                $values = $data->values ?? [];
                $projects = array_merge($projects, $values);
                $start += count($values);
                if (($data->isLast ?? false) || !$values || (isset($data->total) && $start >= $data->total)) {
                    return $projects;
                }
            }
            throw ValidationException::withMessages(['host' => 'Demasiados proyectos para una sola consulta.']);
        } catch (GuzzleException | \JsonException $e) {
            Log::warning('Jira project request failed', ['code' => $e->getCode()]);
            return null;
        }
    }

    public function getJiraTicketsByProject(Client $client, $projectKeys): ?array
    {
        try {
            $results = [];
            foreach ((array) $projectKeys as $projectKey) {
                $projectKey = JiraEndpoint::projectKey((string) $projectKey);
                $issues = []; $token = null; $seen = [];
                for ($page = 0; $page < config('jira.max_pages', 100); $page++) {
                    $query = ['jql' => 'project = "' . $projectKey . '" ORDER BY key',
                        'maxResults' => 100, 'fields' => 'summary,project'];
                    if ($token) $query['nextPageToken'] = $token;
                    $data = json_decode((string) $client->get('rest/api/3/search/jql', ['query' => $query])->getBody(), false, 512, JSON_THROW_ON_ERROR);
                    foreach ($data->issues ?? [] as $issue) {
                        $key = JiraEndpoint::issueKey((string) $issue->key);
                        $issues[$key] = ['code' => $key, 'name' => $issue->fields->summary ?? $key, 'data' => $issue];
                    }
                    $next = $data->nextPageToken ?? null;
                    if (($data->isLast ?? false) || !$next) {
                        $results[$projectKey] = ['total' => count($issues), 'issues' => array_values($issues)];
                        continue 2;
                    }
                    if (isset($seen[$next])) throw ValidationException::withMessages(['data' => 'Jira repitió un token de paginación.']);
                    $seen[$next] = true; $token = $next;
                }
                throw ValidationException::withMessages(['data' => 'La consulta excede el límite de páginas.']);
            }
            return $results;
        } catch (GuzzleException | \JsonException $e) {
            Log::warning('Jira issue request failed', ['code' => $e->getCode()]);
            return null;
        }
    }

    public function getJiraTicketDetails($host, $username, $token, $key)
    {
        $key = JiraEndpoint::issueKey((string) $key);
        try {
            $client = $this->connectToJira($host, $username, $token);
            return json_decode((string) $client->get('rest/api/3/issue/' . rawurlencode($key))->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException | \JsonException $e) {
            Log::warning('Jira issue detail request failed', ['code' => $e->getCode()]);
            return null;
        }
    }
}
