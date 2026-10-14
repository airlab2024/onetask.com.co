<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jeffgreco13\FilamentBreezy\Models\BreezySession;

class BreezyEnrollmentSync
{
    public static function updateLegacyColumns(BreezySession $session, bool $removed = false): void
    {
        if ($session->authenticatable_type !== User::class || !Schema::hasColumn('users', 'two_factor_secret')) {
            return;
        }
        DB::table('users')->where('id', $session->authenticatable_id)->update([
            'two_factor_secret' => $removed ? null : $session->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $removed ? null : $session->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $removed ? null : $session->getRawOriginal('two_factor_confirmed_at'),
        ]);
    }
}
