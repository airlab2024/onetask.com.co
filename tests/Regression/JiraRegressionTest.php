<?php

namespace Tests\Regression;

use App\Filament\Pages\JiraImport;
use App\Helpers\JiraHelper;
use App\Support\JiraEndpoint;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JiraRegressionTest extends IsolatedTestCase
{
    public function test_endpoint_rejects_credential_exfiltration_and_internal_hosts(): void
    {
        $this->assertSame('https://team.atlassian.net/', JiraEndpoint::normalize('https://team.atlassian.net'));
        foreach (['http://team.atlassian.net', 'https://127.0.0.1', 'https://localhost',
            'https://team.atlassian.net.evil.example', 'https://u:p@team.atlassian.net',
            'https://team.atlassian.net:8443', 'https://team.atlassian.net/private'] as $host) {
            try {
                JiraEndpoint::normalize($host);
                $this->fail('Invalid host accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('host', $e->errors());
            }
        }
    }

    public function test_paginated_issue_search_collects_every_page(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['issues' => [['key' => 'ABC-1', 'fields' => ['summary' => 'First']]], 'nextPageToken' => 'second'])),
            new Response(200, [], json_encode(['issues' => [['key' => 'ABC-2', 'fields' => ['summary' => 'Second']]], 'isLast' => true])),
        ]);
        $client = new Client(['base_uri' => 'https://team.atlassian.net/', 'handler' => HandlerStack::create($mock)]);
        $helper = new class { use JiraHelper; };
        $issues = $helper->getJiraTicketsByProject($client, ['ABC']);
        $this->assertSame(['ABC-1', 'ABC-2'], array_column($issues['ABC']['issues'], 'code'));
        $this->assertSame(2, $issues['ABC']['total']);
        $this->assertStringContainsString('nextPageToken=second', (string) $mock->getLastRequest()->getUri());
    }

    public function test_jql_project_injection_is_rejected_before_network_access(): void
    {
        $helper = new class { use JiraHelper; };
        $this->expectException(ValidationException::class);
        $helper->getJiraTicketsByProject(new Client(), ['ABC OR 1=1']);
    }

    public function test_adf_description_preserves_text_without_executable_html(): void
    {
        $html = JiraEndpoint::description((object) ['content' => [(object) ['text' => '<script>text</script>']]]);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_direct_import_without_permission_is_rejected(): void
    {
        $this->actingAs(7);
        $this->expectException(HttpException::class);
        (new JiraImport())->import();
    }

    public function test_unchecked_issues_are_not_imported(): void
    {
        $this->actingAs(7, ['Import from Jira']);
        $page = new JiraImport(); $page->host = 'https://team.atlassian.net';
        $page->data = ['abc_abc-1' => false, 'abc_abc-2' => 0];
        Cache::put('jira-selection:7:' . hash('sha256', session()->getId()),
            ['host' => 'https://team.atlassian.net/', 'projects' => ['ABC'], 'issues' => ['abc_abc-1' => 'ABC-1']]);
        $this->expectException(ValidationException::class);
        $page->import();
    }
}
