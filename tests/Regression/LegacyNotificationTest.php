<?php

namespace Tests\Regression;

use App\Casts\LegacyNotificationData;
use App\Models\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyNotificationTest extends IsolatedTestCase
{
    public function test_old_links_and_icons_render_in_the_current_environment_without_rewriting_storage(): void
    {
        config(['app.url' => 'https://pruebas.onetask.com.co']);
        Schema::create('notifications', function ($table): void {
            $table->string('id')->primary(); $table->string('type'); $table->morphs('notifiable');
            $table->text('data'); $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        $data = ['format' => 'filament', 'icon' => 'heroicon-o-document-report',
            'actions' => [['color' => 'secondary', 'url' => 'http://onetask.com.co/onetask/public/tasks/42']]];
        $raw = json_encode($data);
        DB::table('notifications')->insert(['id' => 'fixture', 'type' => 'fixture', 'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => 42, 'data' => $raw, 'created_at' => now(), 'updated_at' => now()]);
        $notification = DatabaseNotification::findOrFail('fixture');
        $this->assertSame('heroicon-o-document-chart-bar', $notification->data['icon']);
        $this->assertSame('gray', $notification->data['actions'][0]['color']);
        $this->assertSame('https://pruebas.onetask.com.co/tasks/42', $notification->data['actions'][0]['url']);
        $notification->markAsRead();
        $this->assertSame($raw, DB::table('notifications')->value('data'));
    }

    public function test_external_urls_and_message_text_are_not_rewritten(): void
    {
        $data = ['body' => 'https://task.onetask.com.co/tasks/42', 'actions' => [['url' => 'https://example.com/tasks/42']]];
        $this->assertSame($data, LegacyNotificationData::normalize($data));
    }
}
