<?php

namespace Tests\Regression;

use App\Support\AccountActivation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountActivationTest extends IsolatedTestCase
{
    private function invitation(): void
    {
        Schema::table('users', function ($table): void {
            $table->string('password'); $table->string('creation_token')->nullable();
            $table->timestamp('email_verified_at')->nullable(); $table->timestamps();
        });
        DB::table('users')->insert(['id' => 42, 'name' => 'Invited user', 'password' => Hash::make('old-password'), 'creation_token' => 'single-use-token']);
    }

    public function test_invitation_sets_password_and_cannot_be_replayed(): void
    {
        $this->invitation();
        AccountActivation::activate(42, 'single-use-token', 'new-password');
        $this->assertNull(DB::table('users')->value('creation_token'));
        $this->assertTrue(Hash::check('new-password', DB::table('users')->value('password')));
        $this->assertNotNull(DB::table('users')->value('email_verified_at'));
        try {
            AccountActivation::activate(42, 'single-use-token', 'replayed-password');
            $this->fail('Consumed invitations must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertTrue(Hash::check('new-password', DB::table('users')->value('password')));
    }

    public function test_another_token_cannot_change_the_account(): void
    {
        $this->invitation();
        try {
            AccountActivation::activate(42, 'different-token', 'unwanted-password');
            $this->fail('Invalid invitation must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('single-use-token', DB::table('users')->value('creation_token'));
        $this->assertTrue(Hash::check('old-password', DB::table('users')->value('password')));
    }
}
