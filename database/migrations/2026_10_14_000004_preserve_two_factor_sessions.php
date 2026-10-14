<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('breezy_sessions')) {
            Schema::create('breezy_sessions', function (Blueprint $table): void {
                $table->id(); $table->morphs('authenticatable');
                $table->string('panel_id')->nullable(); $table->string('guard')->nullable();
                $table->string('ip_address', 45)->nullable(); $table->text('user_agent')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('two_factor_secret')->nullable(); $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasColumn('users', 'two_factor_secret')) {
            return;
        }
        // Copy ciphertext unchanged; the APP_KEY and legacy user columns remain
        // available so switching versions never requires resetting users' 2FA.
        DB::table('users')->whereNotNull('two_factor_secret')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $key = ['authenticatable_type' => \App\Models\User::class, 'authenticatable_id' => $user->id];
                if (DB::table('breezy_sessions')->where($key)->exists()) {
                    continue;
                }
                DB::table('breezy_sessions')->insert($key + [
                    'panel_id' => 'onetask', 'guard' => 'web',
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve sessions and enrollment changes made after the upgrade.
        // A rollback must not delete users' second-factor secrets.
    }
};
