<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StemcraftPunishmentsPageTest extends TestCase
{
    use RefreshDatabase;

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
                $table->uuid('by_user_id')->nullable();
                $table->string('by_username', 80)->nullable();
                $table->timestamp('lifted_at')->nullable();
                $table->string('lifted_by_uuid', 64)->nullable();
                $table->uuid('lifted_by_user_id')->nullable();
                $table->string('lifted_by_username', 80)->nullable();
                $table->text('lift_reason')->nullable();
                $table->softDeletes();
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
        $accountId = DB::table('minecraft_accounts')->insertGetId([
            'platform' => 'bedrock',
            'uuid' => 'uuid-one',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('minecraft_penalties')->insert([
            [
                'minecraft_account_id' => $accountId,
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
                'minecraft_account_id' => null,
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
            [
                'minecraft_account_id' => null,
                'external_id' => 'stemcraft-test-3',
                'uuid' => 'uuid-three',
                'username' => 'PlayerThree',
                'type' => 'warn',
                'reason' => 'Final warning',
                'started_at' => now()->subHours(3),
                'ends_at' => null,
                'is_permanent' => false,
                'by_username' => 'ModeratorC',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get('/stemcraft/punishments?search=PlayerOne&type=ban');

        $response->assertOk();
        $response->assertSee('PlayerOne');
        $response->assertDontSee('PlayerTwo');
        $response->assertSee('Griefing');
        $response->assertSee('(bedrock)');

        $warningResponse = $this->get('/stemcraft/punishments?search=PlayerThree&type=warn');
        $warningResponse->assertOk();
        $warningResponse->assertSee('PlayerThree');
        $warningResponse->assertSeeText('Warning');
        $warningResponse->assertSeeText('Recorded');
    }
}
