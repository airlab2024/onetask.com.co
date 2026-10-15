<?php

namespace App\Support;

class ExternalLoginConfiguration
{
    public static function social(string $provider): bool
    {
        return (bool) config('filament-socialite.enabled')
            && self::complete($provider, ['client_id', 'client_secret', 'redirect']);
    }

    public static function oidc(): bool
    {
        return (bool) config('services.oidc.is_enabled')
            && self::complete('oidc', ['client_id', 'client_secret', 'redirect_uri', 'url_authorize', 'url_access_token', 'url_resource_owner_details']);
    }

    private static function complete(string $provider, array $keys): bool
    {
        foreach ($keys as $key) {
            $value = config("services.{$provider}.{$key}");
            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }
        return true;
    }
}
