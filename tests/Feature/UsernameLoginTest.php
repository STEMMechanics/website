<?php

namespace Tests\Feature;

use App\Models\Token;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

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

        User::query()->whereNull('username')->get()->each(function (User $user): void {
            $user->username = User::generateUniqueUsernameFromEmail((string) $user->email, (string) $user->id);
            $user->save();
        });

        Token::query()->where('type', 'login')->delete();
        User::query()->where('email', 'member@example.com')->delete();
    }

    public function test_verified_user_can_start_login_flow_with_username(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'username' => 'member1',
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'member1',
            'remember_email' => '0',
        ]);

        $response->assertOk();
        $response->assertSee('Check your inbox');

        $this->assertDatabaseHas('tokens', [
            'user_id' => $user->id,
            'type' => 'login',
        ]);

        $this->assertNotNull(Token::query()->where('user_id', $user->id)->where('type', 'login')->first());
    }
}
