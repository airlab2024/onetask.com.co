<?php

namespace Tests\Regression;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TwoFactorMigrationTest extends IsolatedTestCase
{
    public function test_enrollment_changes_keep_rollback_columns_in_sync(): void
    {
        Schema::table('users', function ($table): void {
            $table->text('two_factor_secret')->nullable(); $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        DB::table('users')->insert(['id' => 42, 'name' => 'Enrolled user']);
        $session = \Jeffgreco13\FilamentBreezy\Models\BreezySession::withoutEvents(fn () =>
            \Jeffgreco13\FilamentBreezy\Models\BreezySession::create(['authenticatable_type' => \App\Models\User::class,
                'authenticatable_id' => 42, 'two_factor_secret' => 'new-encrypted-secret', 'two_factor_recovery_codes' => 'new-recovery',
                'two_factor_confirmed_at' => '2026-10-14 12:00:00']));
        \App\Support\BreezyEnrollmentSync::updateLegacyColumns($session);
        $this->assertSame('new-encrypted-secret', DB::table('users')->value('two_factor_secret'));
        $this->assertSame('new-recovery', DB::table('users')->value('two_factor_recovery_codes'));
        \App\Support\BreezyEnrollmentSync::updateLegacyColumns($session, true);
        $this->assertNull(DB::table('users')->value('two_factor_secret'));
    }

    public function test_upgrade_preserves_ciphertext_and_does_not_duplicate_enrollment(): void
    {
        Schema::table('users', function ($table): void {
            $table->text('two_factor_secret')->nullable(); $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        DB::table('users')->insert(['id' => 42, 'name' => 'Enrolled user', 'two_factor_secret' => 'existing-encrypted-secret',
            'two_factor_recovery_codes' => 'existing-encrypted-recovery', 'two_factor_confirmed_at' => '2025-10-01 12:00:00']);
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_14_000004_preserve_two_factor_sessions.php';
        $migration->up(); $migration->up();
        $this->assertSame(1, DB::table('breezy_sessions')->count());
        $this->assertSame('existing-encrypted-secret', DB::table('breezy_sessions')->value('two_factor_secret'));
        $this->assertSame('existing-encrypted-recovery', DB::table('breezy_sessions')->value('two_factor_recovery_codes'));
        $this->assertSame('2025-10-01 12:00:00', DB::table('breezy_sessions')->value('two_factor_confirmed_at'));
        $this->assertSame('existing-encrypted-secret', DB::table('users')->value('two_factor_secret'));
        $migration->down();
        $this->assertSame(1, DB::table('breezy_sessions')->count());
    }
}
