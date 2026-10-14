<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class JiraEndpoint
{
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');
        $allowed = preg_match('/^[a-z0-9-]+\.atlassian\.net$/', $host)
            || in_array($host, config('jira.allowed_hosts', []), true);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !$allowed
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)
            || (($parts['port'] ?? 443) !== 443) || filter_var($host, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['host' => 'Usa una URL HTTPS de Jira Cloud o un servidor autorizado en JIRA_ALLOWED_HOSTS.']);
        }
        return 'https://' . $host . '/';
    }

    public static function projectKey(string $key): string
    {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw ValidationException::withMessages(['selected_projects' => 'Clave de proyecto Jira no válida.']);
        }
        return $key;
    }

    public static function issueKey(string $key): string
    {
        if (!preg_match('/^[A-Z][A-Z0-9_]*-[0-9]+$/', $key)) {
            throw ValidationException::withMessages(['data' => 'Clave de incidencia Jira no válida.']);
        }
        return $key;
    }

    public static function description($description): string
    {
        if (is_string($description)) {
            return $description;
        }
        $parts = [];
        $walk = function ($node) use (&$walk, &$parts): void {
            $node = (array) $node;
            if (isset($node['text']) && is_string($node['text'])) {
                $parts[] = $node['text'];
            }
            foreach ($node['content'] ?? [] as $child) {
                $walk($child);
            }
        };
        $walk($description);
        return nl2br(e(implode("\n", $parts)));
    }
}
