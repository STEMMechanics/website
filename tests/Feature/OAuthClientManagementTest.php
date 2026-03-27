<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken as PassportRefreshToken;
use Laravel\Passport\Token as PassportToken;
use Tests\TestCase;

class OAuthClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_oauth_clients_from_the_admin_page(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->post(route('admin.oauth-clients.store'), [
                'name' => 'Gitea',
                'redirect_uris' => 'https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback',
            ])
            ->assertRedirect(route('admin.oauth-clients.index'));

        $this->actingAs($admin)
            ->get(route('admin.oauth-clients.index'))
            ->assertOk()
            ->assertSeeText('OAuth Clients')
            ->assertSeeText('Gitea');

        $this->assertDatabaseHas('oauth_clients', [
            'name' => 'Gitea',
            'revoked' => 0,
        ]);

        $client = PassportClient::query()->where('name', 'Gitea')->firstOrFail();
        $this->assertSame(['authorization_code', 'refresh_token'], $client->grant_types);
        $this->assertNotEmpty($client->secret);
    }

    public function test_admin_can_rotate_and_revoke_oauth_clients(): void
    {
        $admin = $this->createAdminUser();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Gitea',
            ['https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback']
        );

        $originalSecret = $client->fresh()->secret;

        $this->actingAs($admin)
            ->post(route('admin.oauth-clients.rotate-secret', $client))
            ->assertRedirect(route('admin.oauth-clients.index'));

        $this->assertNotSame($originalSecret, $client->fresh()->secret);

        $accessTokenId = Str::random(80);
        $refreshTokenId = Str::random(80);
        PassportToken::query()->create([
            'id' => $accessTokenId,
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'name' => 'Gitea',
            'scopes' => ['openid', 'profile', 'email'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
        PassportRefreshToken::query()->create([
            'id' => $refreshTokenId,
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.oauth-clients.destroy', $client))
            ->assertRedirect(route('admin.oauth-clients.index'));

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $client->id,
            'revoked' => 1,
        ]);

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $accessTokenId,
            'revoked' => 1,
        ]);

        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $refreshTokenId,
            'revoked' => 1,
        ]);
    }

    public function test_admin_can_edit_oauth_client_name_and_redirects(): void
    {
        $admin = $this->createAdminUser();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Gitea',
            ['https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback']
        );

        $response = $this->actingAs($admin)->get(route('admin.oauth-clients.edit', $client));

        $response->assertOk();
        $response->assertSeeText('Edit OAuth Client');
        $response->assertSeeText('Gitea');

        $this->actingAs($admin)
            ->put(route('admin.oauth-clients.update', $client), [
                'name' => 'Gitea Login',
                'redirect_uris' => "https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback\nhttps://git.stemmechanics.com.au/user/oauth2/STEMMechanics/alt-callback",
            ])
            ->assertRedirect(route('admin.oauth-clients.edit', $client));

        $client->refresh();

        $this->assertSame('Gitea Login', $client->name);
        $this->assertSame([
            'https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback',
            'https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/alt-callback',
        ], $client->redirect_uris);
        $this->assertSame(['authorization_code', 'refresh_token'], $client->grant_types);
    }

    public function test_user_can_view_and_disconnect_connected_apps(): void
    {
        $user = User::factory()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Gitea',
            ['https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback']
        );

        [$accessTokenId, $refreshTokenId] = $this->createPassportGrant($user, $client);

        $response = $this->actingAs($user)->get(route('account.oauth-apps.index'));

        $response->assertOk();
        $response->assertSeeText('Connected Apps');
        $response->assertSeeText('Gitea');
        $response->assertSeeText('Disconnect');

        $this->actingAs($user)
            ->delete(route('account.oauth-apps.destroy', $client))
            ->assertRedirect(route('account.oauth-apps.index'));

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $accessTokenId,
            'revoked' => 1,
        ]);

        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $refreshTokenId,
            'revoked' => 1,
        ]);
    }

    public function test_user_can_disconnect_all_connected_apps(): void
    {
        $user = User::factory()->create();
        $firstClient = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Gitea',
            ['https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback']
        );
        $secondClient = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Mattermost',
            ['https://chat.stemmechanics.com.au/oauth/callback']
        );

        [$firstAccessTokenId, $firstRefreshTokenId] = $this->createPassportGrant($user, $firstClient);
        [$secondAccessTokenId, $secondRefreshTokenId] = $this->createPassportGrant($user, $secondClient);

        $this->actingAs($user)
            ->delete(route('account.oauth-apps.destroy-all'))
            ->assertRedirect(route('account.oauth-apps.index'));

        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $firstAccessTokenId,
            'revoked' => 1,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $firstRefreshTokenId,
            'revoked' => 1,
        ]);
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $secondAccessTokenId,
            'revoked' => 1,
        ]);
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'id' => $secondRefreshTokenId,
            'revoked' => 1,
        ]);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createPassportGrant(User $user, PassportClient $client): array
    {
        $accessTokenId = Str::random(80);
        $refreshTokenId = Str::random(80);

        PassportToken::query()->create([
            'id' => $accessTokenId,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => $client->name,
            'scopes' => ['openid', 'profile', 'email'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        PassportRefreshToken::query()->create([
            'id' => $refreshTokenId,
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        return [$accessTokenId, $refreshTokenId];
    }
}
