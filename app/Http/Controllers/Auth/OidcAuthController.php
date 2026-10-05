<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;

class OidcAuthController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new GenericProvider([
            'clientId' => config('services.oidc.client_id'),
            'clientSecret' => config('services.oidc.client_secret'),
            'redirectUri' => config('services.oidc.redirect_uri'),
            'urlAuthorize' => config('services.oidc.url_authorize'),
            'urlAccessToken' => config('services.oidc.url_access_token'),
            'urlResourceOwnerDetails' => config('services.oidc.url_resource_owner_details'),
            'scopes' => config('services.oidc.scope')
        ]);
    }

    public function redirect()
    {
        abort_unless(config('services.oidc.is_enabled'), 404);
        $authUrl = $this->client->getAuthorizationUrl();
        session()->put('oidc_state', $this->client->getState());
        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        abort_unless(config('services.oidc.is_enabled'), 404);
        $expected = $request->session()->pull('oidc_state');
        $state = $request->input('state');
        abort_unless(is_string($expected) && $expected !== '' && is_string($state) && hash_equals($expected, $state), 403);
        abort_unless(is_string($request->input('code')) && $request->input('code') !== '', 400);
        try {
            $accessToken = $this->client->getAccessToken('authorization_code', ['code' => $request->input('code')]);
            $data = $this->client->getResourceOwner($accessToken)->toArray();
            $subject = $data['sub'] ?? null;
            $email = $data['email'] ?? null;
            if (!is_string($subject) || $subject === '' || strlen($subject) > 255
                || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || !filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return $this->loginError();
            }
            $user = User::where('type', 'oidc')->where('oidc_sub', $subject)->first();
            if (!$user) {
                // Allow a verified legacy OIDC identity to acquire its subject.
                // Never silently convert a password account or replace its password.
                $user = User::where('type', 'oidc')->whereNull('oidc_sub')->where('email', $email)->first();
            }
            if (User::where('email', $email)->when($user, fn ($query) => $query->where('id', '!=', $user->id))->exists()) {
                return $this->loginError();
            }
            $identity = [
                'name' => trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? '')) ?: $email,
                'email' => $email,
                'oidc_username' => $data['preferred_username'] ?? $subject,
                'oidc_sub' => $subject,
                'email_verified_at' => now(),
                'type' => 'oidc',
            ];
            if (!$user) {
                $settings = app(GeneralSettings::class);
                if (!$settings->enable_registration) {
                    return $this->loginError();
                }
                $user = User::create($identity + ['password' => null]);
                if ($settings->default_role && $role = Role::find($settings->default_role)) {
                    $user->syncRoles([$role]);
                }
            } else {
                $user->update($identity);
            }
            auth()->login($user);
            $request->session()->regenerate();
            return redirect()->intended(route('filament.pages.dashboard'));
        } catch (IdentityProviderException $e) {
            return $this->loginError();
        }
    }

    private function loginError()
    {
        session()->flash('oidc_error', true);
        return redirect()->route('login');
    }
}
