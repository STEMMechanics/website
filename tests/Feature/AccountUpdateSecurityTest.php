<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountUpdateSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->boolean('admin')->default(false);
                $table->string('firstname')->nullable();
                $table->string('surname')->nullable();
                $table->string('company')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->unique();
                $table->string('username', 32)->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->text('tfa_secret')->nullable();
                $table->boolean('agree_tos')->default(false);
                $table->string('shipping_address')->nullable();
                $table->string('shipping_address2')->nullable();
                $table->string('shipping_city')->nullable();
                $table->string('shipping_state')->nullable();
                $table->string('shipping_postcode')->nullable();
                $table->string('shipping_country')->nullable();
                $table->string('billing_address')->nullable();
                $table->string('billing_address2')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_postcode')->nullable();
                $table->string('billing_country')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('users', 'username')) {
            try {
                Schema::table('users', function (Blueprint $table): void {
                    $table->string('username', 32)->nullable()->after('email');
                });
            } catch (QueryException) {
            }
        }

        if (! Schema::hasTable('tokens')) {
            Schema::create('tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type');
                $table->json('data')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('email_subscriptions')) {
            Schema::create('email_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->string('email');
                $table->timestamp('confirmed')->nullable();
                $table->timestamps();
            });
        }

        User::query()->whereNull('username')->get()->each(function (User $user): void {
            $user->username = User::generateUniqueUsernameFromEmail((string) $user->email, (string) $user->id);
            $user->save();
        });
    }

    public function test_account_update_cannot_mass_assign_sensitive_user_fields(): void
    {
        $user = User::factory()->create([
            'tfa_secret' => null,
            'agree_tos' => 0,
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('account.update'), [
                '_token' => 'test-csrf-token',
                'email' => $user->email,
                'username' => $user->username,
                'groups' => 'admin',
                'tfa_secret' => 'FORGED',
                'email_verified_at' => now()->subYear()->toDateTimeString(),
                'agree_tos' => 1,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->tfa_secret);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame(1, (int) $user->agree_tos);
    }
}
