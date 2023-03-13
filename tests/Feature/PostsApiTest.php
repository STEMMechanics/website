<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Media;
use App\Models\Post;
use Faker\Factory as FakerFactory;

class PostsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = FakerFactory::create();
    }
    
    public function testAnyUserCanViewPost()
    {
        // Create an event
        $post = Post::factory()->create([
            'publish_at' => $this->faker->dateTimeBetween('-2 months', '-1 month'),
        ]);

        // Create a future event
        $futurePost = Post::factory()->create([
            'publish_at' => $this->faker->dateTimeBetween('+1 month', '+2 months'),
        ]);

        // Send GET request to the /api/posts endpoint
        $response = $this->getJson('/api/posts');
        $response->assertStatus(200);

        // Assert that the event is in the response data
        $response->assertJsonCount(1, 'posts');
        $response->assertJsonFragment([
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content,
        ]);

        $response->assertJsonMissing([
            'id' => $futurePost->id,
            'title' => $futurePost->title,
            'content' => $futurePost->content,
        ]);
    }

    public function testAdminCanCreateUpdateDeletePost()
    {
        // Create a user with the admin/events permission
        $adminUser = User::factory()->create();
        $adminUser->givePermission('admin/posts');
    
        // Create media data
        $media = Media::factory()->create(['user_id' => $adminUser->id]);

        // Create event data
        $postData = Post::factory()->make([
            'user_id' => $adminUser->id,
            'hero' => $media->id,
        ])->toArray();

        // Test creating event
        $response = $this->actingAs($adminUser)->postJson('/api/posts', $postData);
        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => $postData['title'],
            'content' => $postData['content'],
        ]);
    
        // Test viewing event
        $post = Post::where('title', $postData['title'])->first();
        $response = $this->get("/api/posts/$post->id");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'post' => [
            'id',
            'title',
            'content',
            ]
        ]);
    
        // Test updating event
        $postData['title'] = 'Updated Post';
        $response = $this->actingAs($adminUser)->putJson("/api/posts/$post->id", $postData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'title' => 'Updated Post',
        ]);
    
        // Test deleting event
        $response = $this->actingAs($adminUser)->delete("/api/posts/$post->id");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Updated Post',
        ]);
    }

    public function testNonAdminCannotCreateUpdateDeletePost()
    {
        // Create a user without admin/events permission
        $user = User::factory()->create();

        // Authenticate as the user
        $this->actingAs($user);

        // Try to create a new post
        $media = Media::factory()->create(['user_id' => $user->id]);

        $newPostData = Post::factory()->make(['user_id' => $user->id, 'hero' => $media->id])->toArray();

        $response = $this->postJson('/api/posts', $newPostData);
        $response->assertStatus(403);

        // Try to update an event
        $post = Post::factory()->create();
        $updatedPostData = [
            'title' => 'Updated Event',
            'content' => 'This is an updated event.',
            // Add more fields as needed
        ];
        $response = $this->putJson('/api/posts/' . $post->id, $updatedPostData);
        $response->assertStatus(403);

        // Try to delete an event
        $post = Post::factory()->create();
        $response = $this->deleteJson('/api/posts/' . $post->id);
        $response->assertStatus(403);
    }
}
