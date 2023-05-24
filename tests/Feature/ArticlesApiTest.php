<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Media;
use App\Models\Article;
use Faker\Factory as FakerFactory;

final class ArticlesApiTest extends TestCase
{
    use RefreshDatabase;

    protected $faker;


    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = FakerFactory::create();
    }

    public function testAnyUserCanViewArticle(): void
    {
        // Create an event
        $article = Article::factory()->create([
            'publish_at' => $this->faker->dateTimeBetween('-2 months', '-1 month'),
        ]);

        // Create a future event
        $futureArticle = Article::factory()->create([
            'publish_at' => $this->faker->dateTimeBetween('+1 month', '+2 months'),
        ]);

        // Send GET request to the /api/articles endpoint
        $response = $this->getJson('/api/articles');
        $response->assertStatus(200);

        // Assert that the event is in the response data
        $response->assertJsonCount(1, 'articles');
        $response->assertJsonFragment([
            'id' => $article->id,
            'title' => $article->title,
            'content' => $article->content,
        ]);

        $response->assertJsonMissing([
            'id' => $futureArticle->id,
            'title' => $futureArticle->title,
            'content' => $futureArticle->content,
        ]);
    }

    public function testAdminCanCreateUpdateDeleteArticle(): void
    {
        // Create a user with the admin/events permission
        $adminUser = User::factory()->create();
        $adminUser->givePermission('admin/articles');

        // Create media data
        $media = Media::factory()->create(['user_id' => $adminUser->id]);

        // Create event data
        $articleData = Article::factory()->make([
            'user_id' => $adminUser->id,
            'hero' => $media->id,
        ])->toArray();

        // Test creating event
        $response = $this->actingAs($adminUser)->postJson('/api/articles', $articleData);
        $response->assertStatus(201);
        $this->assertDatabaseHas('articles', [
            'title' => $articleData['title'],
            'content' => $articleData['content'],
        ]);

        // Test viewing event
        $article = Article::where('title', $articleData['title'])->first();
        $response = $this->get("/api/articles/$article->id");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'article' => [
                'id',
                'title',
                'content',
            ]
        ]);

        // Test updating event
        $articleData['title'] = 'Updated Article';
        $response = $this->actingAs($adminUser)->putJson("/api/articles/$article->id", $articleData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('articles', [
            'title' => 'Updated Article',
        ]);

        // Test deleting event
        $response = $this->actingAs($adminUser)->delete("/api/articles/$article->id");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('articles', [
            'title' => 'Updated Article',
        ]);
    }

    public function testNonAdminCannotCreateUpdateDeleteArticle(): void
    {
        // Create a user without admin/events permission
        $user = User::factory()->create();

        // Authenticate as the user
        $this->actingAs($user);

        // Try to create a new article
        $media = Media::factory()->create(['user_id' => $user->id]);

        $newArticleData = Article::factory()->make(['user_id' => $user->id, 'hero' => $media->id])->toArray();

        $response = $this->postJson('/api/articles', $newArticleData);
        $response->assertStatus(403);

        // Try to update an event
        $article = Article::factory()->create();
        $updatedArticleData = [
            'title' => 'Updated Event',
            'content' => 'This is an updated event.',
            // Add more fields as needed
        ];
        $response = $this->putJson('/api/articles/' . $article->id, $updatedArticleData);
        $response->assertStatus(403);

        // Try to delete an event
        $article = Article::factory()->create();
        $response = $this->deleteJson('/api/articles/' . $article->id);
        $response->assertStatus(403);
    }
}
