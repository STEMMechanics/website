<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\WorkshopInterestAdminNotification;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkshopInterestRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_interest_workshop_page_shows_interest_button_and_count(): void
    {
        $workshop = $this->createInterestWorkshop();
        $firstUser = User::factory()->unverified()->create(['email' => 'first@example.com']);
        $secondUser = User::factory()->unverified()->create(['email' => 'second@example.com']);

        WorkshopInterest::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $firstUser->id,
            'name' => 'First Person',
            'email' => 'first@example.com',
            'phone' => '0400000001',
        ]);
        WorkshopInterest::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $secondUser->id,
            'name' => 'Second Person',
            'email' => 'second@example.com',
            'phone' => '0400000002',
        ]);

        $this->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSeeText("I'm Interested", false)
            ->assertSeeText('3 interested so far', false);
    }

    public function test_logged_in_interest_workshop_page_shows_direct_toggle_without_popup_fields(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Coder',
            'email' => 'jamie@example.com',
            'phone' => '0411222333',
        ]);
        $workshop = $this->createInterestWorkshop();

        $this->actingAs($user)
            ->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSee('I&#039;m Interested', false)
            ->assertDontSee('name="interest_name"', false)
            ->assertDontSee('name="interest_email"', false)
            ->assertDontSee('name="interest_phone"', false);
    }

    public function test_guest_can_register_interest_and_creates_ghost_user(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');
        $workshop = $this->createInterestWorkshop();

        $response = $this->post(route('workshop.interest', $workshop), [
            'interest_name' => 'Alex Builder',
            'interest_email' => 'alex@example.com',
            'interest_phone' => '0400000000',
        ]);

        $response->assertRedirect(route('workshop.show', $workshop));
        $response->assertSessionHas('message', 'Thanks, your interest has been recorded.');

        $ghostUser = User::query()->where('email', 'alex@example.com')->first();

        $this->assertNotNull($ghostUser);
        $this->assertNull($ghostUser->email_verified_at);
        $this->assertDatabaseHas('workshop_interests', [
            'workshop_id' => $workshop->id,
            'user_id' => $ghostUser->id,
            'name' => 'Alex Builder',
            'email' => 'alex@example.com',
            'phone' => '0400000000',
        ]);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($workshop): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof WorkshopInterestAdminNotification
                && (string) $job->mailable->workshop->id === (string) $workshop->id
                && $job->mailable->interest->email === 'alex@example.com';
        });
    }

    public function test_guest_can_register_interest_without_phone(): void
    {
        $workshop = $this->createInterestWorkshop();

        $response = $this->post(route('workshop.interest', $workshop), [
            'interest_name' => 'Casey Example',
            'interest_email' => 'casey@example.com',
            'interest_phone' => '',
        ]);

        $response->assertRedirect(route('workshop.show', $workshop));
        $response->assertSessionHas('message', 'Thanks, your interest has been recorded.');

        $ghostUser = User::query()->where('email', 'casey@example.com')->first();

        $this->assertNotNull($ghostUser);
        $this->assertDatabaseHas('workshop_interests', [
            'workshop_id' => $workshop->id,
            'user_id' => $ghostUser->id,
            'name' => 'Casey Example',
            'email' => 'casey@example.com',
            'phone' => '',
        ]);
    }

    public function test_logged_in_user_interest_uses_their_account_link_and_can_cancel(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $user = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Coder',
            'email' => 'jamie@example.com',
            'phone' => '0411222333',
        ]);
        $workshop = $this->createInterestWorkshop();

        $this->actingAs($user)
            ->post(route('workshop.interest', $workshop), [
                'action' => 'add',
            ])
            ->assertRedirect(route('workshop.show', $workshop))
            ->assertSessionHas('message', 'Thanks, your interest has been recorded.');

        $this->assertDatabaseHas('workshop_interests', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'name' => 'Jamie Coder',
            'email' => 'jamie@example.com',
            'phone' => '0411222333',
        ]);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($workshop): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof WorkshopInterestAdminNotification
                && (string) $job->mailable->workshop->id === (string) $workshop->id
                && $job->mailable->interest->email === 'jamie@example.com';
        });

        $this->actingAs($user)
            ->post(route('workshop.interest', $workshop), [
                'action' => 'remove',
            ])
            ->assertRedirect(route('workshop.show', $workshop))
            ->assertSessionHas('message', 'Your interest has been removed.');

        $this->assertDatabaseMissing('workshop_interests', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_child_account_interest_email_includes_parent_contact_details(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $parent = User::factory()->create([
            'firstname' => 'Pat',
            'surname' => 'Parent',
            'email' => 'parent@example.com',
            'phone' => '0411000000',
        ]);
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'firstname' => 'Charlie',
            'surname' => 'Child',
            'email' => null,
            'email_verified_at' => null,
            'phone' => '',
        ]);
        $workshop = $this->createInterestWorkshop();

        $this->actingAs($child)
            ->post(route('workshop.interest', $workshop), [
                'action' => 'add',
            ])
            ->assertRedirect(route('workshop.show', $workshop))
            ->assertSessionHas('message', 'Thanks, your interest has been recorded.');

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($workshop): bool {
            if ($job->to !== 'ops@example.com'
                || ! $job->mailable instanceof WorkshopInterestAdminNotification
                || (string) $job->mailable->workshop->id !== (string) $workshop->id) {
                return false;
            }

            $rendered = html_entity_decode(strip_tags($job->mailable->render()));

            return str_contains($rendered, 'Charlie Child (Child Account)')
                && str_contains($rendered, 'Parent name: Pat Parent')
                && str_contains($rendered, 'Parent email: parent@example.com')
                && str_contains($rendered, 'Parent phone: 0411000000');
        });
    }

    public function test_guest_cannot_register_interest_twice_for_same_workshop(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $workshop = $this->createInterestWorkshop();

        $this->post(route('workshop.interest', $workshop), [
            'interest_name' => 'Alex Builder',
            'interest_email' => 'alex@example.com',
            'interest_phone' => '0400000000',
        ])->assertRedirect(route('workshop.show', $workshop));

        $this->post(route('workshop.interest', $workshop), [
            'interest_name' => 'Alex Builder Updated',
            'interest_email' => 'alex@example.com',
            'interest_phone' => '0499999999',
        ])
            ->assertRedirect(route('workshop.show', $workshop))
            ->assertSessionHas('message', 'Your interest has already been recorded.');

        $this->assertSame(1, WorkshopInterest::query()->where('workshop_id', $workshop->id)->count());
        $this->assertDatabaseHas('workshop_interests', [
            'workshop_id' => $workshop->id,
            'name' => 'Alex Builder',
            'email' => 'alex@example.com',
            'phone' => '0400000000',
        ]);

        Queue::assertPushed(SendEmail::class, 1);
    }

    public function test_admin_can_view_interest_registrations_on_workshop_interest_page(): void
    {
        $admin = $this->createAdminUser();
        $parent = User::factory()->create([
            'firstname' => 'Pat',
            'surname' => 'Parent',
            'email' => 'parent@example.com',
            'phone' => '0411000000',
        ]);
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'firstname' => 'Charlie',
            'surname' => 'Child',
            'email' => null,
            'email_verified_at' => null,
            'phone' => '',
        ]);
        $guestUser = User::factory()->unverified()->create([
            'firstname' => 'Guest',
            'surname' => 'Example',
            'email' => 'guest@example.com',
            'phone' => '0400000001',
        ]);
        $workshop = $this->createInterestWorkshop();

        WorkshopInterest::query()->forceCreate([
            'workshop_id' => $workshop->id,
            'user_id' => $child->id,
            'name' => 'Charlie Child',
            'email' => '',
            'phone' => '',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        WorkshopInterest::query()->forceCreate([
            'workshop_id' => $workshop->id,
            'user_id' => $guestUser->id,
            'name' => 'Guest Example',
            'email' => 'guest@example.com',
            'phone' => '0400000001',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.interests', $workshop))
            ->assertOk()
            ->assertSeeText('Interest Registrations')
            ->assertSeeText('Charlie Child')
            ->assertSeeText('Parent contact: Pat Parent')
            ->assertSeeText('parent@example.com')
            ->assertSeeText('0411000000')
            ->assertSeeText('Guest Example')
            ->assertSeeText('guest@example.com')
            ->assertSeeText('0400000001');
    }

    public function test_admin_workshop_index_shows_interest_count_link(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createInterestWorkshop();
        $firstUser = User::factory()->unverified()->create(['email' => 'interest-one@example.com']);
        $secondUser = User::factory()->unverified()->create(['email' => 'interest-two@example.com']);

        WorkshopInterest::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $firstUser->id,
            'name' => 'First Interest',
            'email' => 'interest-one@example.com',
            'phone' => '0400000001',
        ]);
        WorkshopInterest::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $secondUser->id,
            'name' => 'Second Interest',
            'email' => 'interest-two@example.com',
            'phone' => '0400000002',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.index'))
            ->assertOk()
            ->assertSee(route('admin.workshop.interests', $workshop), false)
            ->assertSee('title="View interest registrations"', false);
    }

    public function test_admin_workshop_show_page_has_interest_registrations_button(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createInterestWorkshop();
        $ghostUser = User::factory()->unverified()->create(['email' => 'interest@example.com']);

        WorkshopInterest::query()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $ghostUser->id,
            'name' => 'Interest Example',
            'email' => 'interest@example.com',
            'phone' => '0400000001',
        ]);

        $this->actingAs($admin)
            ->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSeeText('View Interests (1)')
            ->assertSee(route('admin.workshop.interests', $workshop), false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInterestWorkshop(array $overrides = []): Workshop
    {
        $author = User::factory()->create();
        $location = Location::factory()->create();
        /** @var Media $hero */
        $hero = Media::factory()->create([
            'name' => 'hero-'.strtolower((string) fake()->unique()->bothify('######')).'.png',
            'mime_type' => 'image/png',
            'user_id' => (string) $author->id,
        ]);
        $startsAt = now()->addDays(7);

        return Workshop::query()->create(array_merge([
            'title' => 'Robotics Interest Workshop',
            'content' => '<p>Robots.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHour(),
            'status' => 'open',
            'price' => 'Free',
            'ages' => '8+',
            'registration' => 'interest',
            'registration_data' => null,
            'private_code' => null,
            'hosted_for' => null,
            'is_private' => false,
            'is_hidden' => false,
            'max_tickets' => null,
            'ticket_group_slug' => null,
            'location_id' => (string) $location->id,
            'user_id' => (string) $author->id,
            'hero_media_name' => (string) $hero->name,
        ], $overrides));
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
