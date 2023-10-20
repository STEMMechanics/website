<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

final class UsersApiTest extends TestCase
{
    use RefreshDatabase;


    /**
     * Tests that non-admin users can only view basic user info.
     *
     * @return void
     */
    public function testNonAdminUsersCanOnlyViewBasicUserInfo(): void
    {
        // create a non-admin user
        $nonAdminUser = User::factory()->create();
        $nonAdminUser->revokePermission('admin/users');

        // create an admin user
        $adminUser = User::factory()->create();
        $adminUser->givePermission('admin/users');

        // ensure the non-admin user can access the endpoint and see basic user info only
        $response = $this->actingAs($nonAdminUser)->get('/api/users');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'users' => [
                '*' => [
                    'id',
                    'display_name'
                ]
            ],
            'total'
        ]);

        $response->assertJsonMissing([
            'users' => [
                '*' => [
                    'email',
                    'password'
                ]
            ],
        ]);

        // ensure the admin user can access the endpoint and see additional user info
        $response = $this->actingAs($adminUser)->get('/api/users');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'users' => [
                '*' => [
                    'id',
                    'email'
                ]
            ],
            'total'
        ]);
        $response->assertJsonMissing([
            'users' => [
                '*' => [
                    'password'
                ]
            ]
        ]);
        $response->assertJsonFragment([
            'id' => $nonAdminUser->id,
            'email' => $nonAdminUser->email
        ]);
    }

    /**
     * Tests that guests cannot create a user via the API.
     *
     * @return void
     */
    public function testGuestCannotCreateUser(): void
    {
        $userData = [
            'email' => 'johndoe@example.com',
            'password' => 'password',
        ];

        $response = $this->postJson('/api/users', $userData);
        $response->assertStatus(401);
        $this->assertDatabaseMissing('users', [
            'email' => $userData['email'],
        ]);
    }

    /**
     * Tests that guests can register a user via the API.
     *
     * @return void
     */
    public function testGuestCanRegisterUser(): void
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'jackdoe',
            'email' => 'johndoe@example.com',
            'password' => 'password',
        ];

        $response = $this->postJson('/api/register', $userData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
        ]);
    }

    /**
     * Tests that duplicate email or display name entries cannot be created.
     *
     * @return void
     */
    public function testCannotCreateDuplicateEmailOrDisplayName(): void
    {
        $userData = [
            'display_name' => 'JackDoe',
            'first_name' => 'Jack',
            'last_name' => 'Doe',
            'email' => 'jackdoe@example.com',
            'password' => 'password',
        ];

        // Test creating user
        $response = $this->postJson('/api/register', $userData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'email' => 'jackdoe@example.com',
        ]);

        // Test creating duplicate user
        $response = $this->postJson('/api/register', $userData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['display_name', 'email']);
    }

    /**
     * Tests that a user can only update their own user info.
     *
     * @return void
     */
    public function testUserCanOnlyUpdateOwnUser(): void
    {
        $user = User::factory()->create();

        $userData = [
            'email' => 'raffi@example.com',
            'password' => 'password',
        ];

        // Test updating own user
        $response = $this->actingAs($user)->putJson('/api/users/' . $user->id, $userData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'raffi@example.com',
        ]);

        // Test updating another user
        $otherUser = User::factory()->create();
        $otherUserData = [
            'email' => 'otherraffi@example.com',
            'password' => 'password',
        ];

        $response = $this->actingAs($user)->putJson('/api/users/' . $otherUser->id, $otherUserData);
        $response->assertStatus(403);
    }

    /**
     * Tests that a user cannot delete users via the API.
     *
     * @return void
     */
    public function testUserCannotDeleteUsers(): void
    {
        $user = User::factory()->create();

        // Test deleting own user
        $response = $this->actingAs($user)->deleteJson('/api/users/' . $user->id);
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Test deleting another user
        $otherUser = User::factory()->create();
        $response = $this->actingAs($user)->deleteJson('/api/users/' . $otherUser->id);
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }

    /**
     * Tests that an admin can update any user's info.
     *
     * @return void
     */
    public function testAdminCanUpdateAnyUser(): void
    {
        $admin = User::factory()->create();
        $admin->givePermission('admin/users');

        $user = User::factory()->create();

        $userData = [
            'email' => 'todddoe@example.com',
            'password' => 'password',
        ];

        // Test updating own user
        $response = $this->actingAs($admin)->putJson('/api/users/' . $user->id, $userData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'todddoe@example.com'
        ]);

        // Test updating another user
        $otherUser = User::factory()->create();
        $otherUserData = [
            'email' => 'kimdoe@example.com',
            'password' => 'password',
        ];

        $response = $this->actingAs($admin)->putJson('/api/users/' . $otherUser->id, $otherUserData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'email' => 'kimdoe@example.com',
        ]);
    }

    /**
     * Tests that an admin can delete any user via the API.
     *
     * @return void
     */
    public function testAdminCanDeleteAnyUser(): void
    {
        $admin = User::factory()->create();
        $admin->givePermission('admin/users');

        $user = User::factory()->create();

        // Test deleting own user
        $response = $this->actingAs($admin)->deleteJson('/api/users/' . $user->id);
        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // Test deleting another user
        $otherUser = User::factory()->create();
        $response = $this->actingAs($admin)->deleteJson('/api/users/' . $otherUser->id);
        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $otherUser->id]);
    }
}
