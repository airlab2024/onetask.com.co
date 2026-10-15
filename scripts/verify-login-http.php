<?php

// Public HTTP smoke test: uses a fresh session and blank credentials only.
// No account is authenticated and no user record is changed.
declare(strict_types=1);

$base = rtrim($argv[1] ?? '', '/');
if (!filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https') {
    fwrite(STDERR, "Usage: php scripts/verify-login-http.php https://application.example\n");
    exit(1);
}

$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 25]);
$request = function (string $url, ?array $payload = null, ?string $csrf = null) use ($client): array {
    curl_setopt_array($client, [CURLOPT_URL => $url, CURLOPT_POST => $payload !== null,
        CURLOPT_HTTPHEADER => $payload === null ? [] : ['Content-Type: application/json', 'Accept: application/json', 'X-Livewire: true', 'X-CSRF-TOKEN: '.$csrf]]);
    if ($payload !== null) {
        curl_setopt($client, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    $body = curl_exec($client);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed: '.curl_error($client));
    }
    return [curl_getinfo($client, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($client, CURLINFO_CONTENT_TYPE), $body];
};

try {
    [$status, , $html] = $request($base.'/login');
    if ($status !== 200) {
        throw new RuntimeException('Login GET returned HTTP '.$status);
    }
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $script = $xpath->query('//script[@src and @data-update-uri]')->item(0);
    $token = $xpath->query('//meta[@name="csrf-token"]')->item(0)?->getAttribute('content');
    if (!$script || !$token) {
        throw new RuntimeException('Livewire script or CSRF token missing');
    }
    $absolute = fn (string $path): string => str_starts_with($path, '/') ? $base.$path : $path;
    $scriptUrl = $absolute($script->getAttribute('src'));
    $updateUrl = $absolute($script->getAttribute('data-update-uri'));
    foreach ([$scriptUrl, $updateUrl] as $url) {
        if (parse_url($url, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST)) {
            throw new RuntimeException('Unexpected cross-origin Livewire URL');
        }
    }
    [$scriptStatus, $type, $javascript] = $request($scriptUrl);
    if ($scriptStatus !== 200 || !str_contains($type, 'javascript') || strlen($javascript) < 50000) {
        throw new RuntimeException('Rendered Livewire script unavailable: HTTP '.$scriptStatus);
    }
    $snapshot = null;
    foreach ($xpath->query('//*[@*[name()="wire:snapshot"]]') as $component) {
        $candidate = $component->getAttribute('wire:snapshot');
        $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        if (str_ends_with($decoded['memo']['name'] ?? '', 'auth.login')) {
            $snapshot = $candidate;
            break;
        }
    }
    if ($snapshot === null) {
        throw new RuntimeException('Login component snapshot missing');
    }
    [$postStatus, , $body] = $request($updateUrl, ['_token' => $token, 'components' => [[
        'snapshot' => $snapshot, 'updates' => ['data.email' => '', 'data.password' => ''],
        'calls' => [['path' => '', 'method' => 'authenticate', 'params' => []]],
    ]]], $token);
    $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $updated = json_decode($response['components'][0]['snapshot'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    $errors = $updated['memo']['errors'] ?? [];
    if ($postStatus !== 200 || !isset($errors['data.email'], $errors['data.password'])) {
        throw new RuntimeException('Blank login did not return field validation: HTTP '.$postStatus);
    }
    echo "PASS: login HTTP 200; rendered JavaScript HTTP 200; Livewire POST HTTP 200 with required-field validation.\n";
    echo 'Script: '.parse_url($scriptUrl, PHP_URL_PATH)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error->getMessage()."\n");
    exit(1);
} finally {
    curl_close($client);
}
