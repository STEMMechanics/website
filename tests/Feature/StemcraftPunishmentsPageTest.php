<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StemcraftPunishmentsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('minecraft_penalties')) {
            Schema::create('minecraft_penalties', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('minecraft_account_id')->nullable();
                $table->string('external_id', 120)->nullable();
                $table->string('uuid', 64);
                $table->string('username', 80);
                $table->string('type', 20);
                $table->text('reason')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_permanent')->default(false);
                $table->string('by_uuid', 64)->nullable();
                $table->string('by_username', 80)->nullable();
                $table->timestamp('lifted_at')->nullable();
                $table->string('lifted_by_uuid', 64)->nullable();
                $table->string('lifted_by_username', 80)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('minecraft_accounts')) {
            Schema::create('minecraft_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id')->nullable();
                $table->string('platform', 20)->nullable();
                $table->string('uuid', 64)->nullable();
                $table->string('username', 80)->nullable();
                $table->boolean('is_whitelisted')->default(true);
                $table->timestamps();
            });
        }

        DB::table('minecraft_penalties')->whereIn('external_id', ['stemcraft-test-1', 'stemcraft-test-2'])->delete();
    }

    protected function tearDown(): void
    {
        DB::table('minecraft_penalties')->whereIn('external_id', ['stemcraft-test-1', 'stemcraft-test-2'])->delete();

        parent::tearDown();
    }

    public function test_punishments_page_displays_and_filters_records(): void
    {
        DB::table('minecraft_penalties')->insert([
            [
                'external_id' => 'stemcraft-test-1',
                'uuid' => 'uuid-one',
                'username' => 'PlayerOne',
                'type' => 'ban',
                'reason' => 'Griefing',
                'started_at' => now()->subDay(),
                'ends_at' => now()->addDay(),
                'is_permanent' => false,
                'by_username' => 'ModeratorA',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_id' => 'stemcraft-test-2',
                'uuid' => 'uuid-two',
                'username' => 'PlayerTwo',
                'type' => 'mute',
                'reason' => 'Spam',
                'started_at' => now()->subDays(2),
                'ends_at' => now()->subDay(),
                'is_permanent' => false,
                'by_username' => 'ModeratorB',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get('/stemcraft/punishments?search=PlayerOne&type=ban');

        $response->assertOk();
        $response->assertSee('PlayerOne');
        $response->assertDontSee('PlayerTwo');
        $response->assertSee('Griefing');
    }
}
