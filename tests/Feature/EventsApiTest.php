<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Media;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;

class EventsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = FakerFactory::create();
    }
    
    public function testAnyUserCanViewEvent()
    {
        // Create an event
        $event = Event::factory()->create([
            'publish_at' => Carbon::parse($this->faker->dateTimeBetween('-2 months', '-1 month')),
        ]);

        // Create a future event
        $futureEvent = Event::factory()->create([
            'publish_at' => Carbon::parse($this->faker->dateTimeBetween('+1 day', '+1 month')),
        ]);

        // Send GET request to the /api/events endpoint
        $response = $this->getJson('/api/events');
        $response->assertStatus(200);

        // Assert that the event is in the response data
        $response->assertJsonCount(1, 'events');
        $response->assertJsonFragment([
            'id' => $event->id,
            'title' => $event->title,
        ]);

        $response->assertJsonMissing([
            'id' => $futureEvent->id,
            'title' => $futureEvent->title,
        ]);
    }

    public function testAdminCanCreateUpdateDeleteEvent()
    {
        // Create a user with the admin/events permission
        $adminUser = User::factory()->create();
        $adminUser->givePermission('admin/events');
    
        // Create media data
        $media = Media::factory()->create(['user_id' => $adminUser->id]);

        // Create event data
        $eventData = Event::factory()->make([
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(2),
            'hero' => $media->id,
        ])->toArray();

        // Test creating event
        $response = $this->actingAs($adminUser)->postJson('/api/events', $eventData);
        $response->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => $eventData['title'],
            'content' => $eventData['content'],
        ]);
    
        // Test viewing event
        $event = Event::where('title', $eventData['title'])->first();
        $response = $this->get("/api/events/$event->id");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'event' => [
            'id',
            'title',
            'content',
            'start_at',
            'end_at',
            ]
        ]);
    
        // Test updating event
        $eventData['title'] = 'Updated Event';
        $response = $this->actingAs($adminUser)->putJson("/api/events/$event->id", $eventData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('events', [
            'title' => 'Updated Event',
        ]);
    
        // Test deleting event
        $response = $this->actingAs($adminUser)->delete("/api/events/$event->id");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('events', [
            'title' => 'Updated Event',
        ]);
    }

    public function testNonAdminCannotCreateUpdateDeleteEvent()
    {
        // Create a user without admin/events permission
        $user = User::factory()->create();

        // Authenticate as the user
        $this->actingAs($user);

        // Try to create a new event
        $media = Media::factory()->create(['user_id' => $user->id]);

        $newEventData = Event::factory()->make(['hero' => $media->id])->toArray();

        $response = $this->postJson('/api/events', $newEventData);
        $response->assertStatus(403);

        // Try to update an event
        $event = Event::factory()->create();
        $updatedEventData = [
            'title' => 'Updated Event',
            'content' => 'This is an updated event.',
            // Add more fields as needed
        ];
        $response = $this->putJson('/api/events/' . $event->id, $updatedEventData);
        $response->assertStatus(403);

        // Try to delete an event
        $event = Event::factory()->create();
        $response = $this->deleteJson('/api/events/' . $event->id);
        $response->assertStatus(403);
    }
}
