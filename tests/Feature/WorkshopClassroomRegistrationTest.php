<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\UserRegister;
use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopClassroomRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['security.altcha_enabled' => false]);
    }

    public function test_admin_can_create_a_classroom_workshop_without_polluting_the_workshop_mast(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $startsAt = now()->addDays(7);

        $response = $this->actingAs($admin)->post(route('admin.workshop.store'), [
            'title' => 'Microbit Term 1 Classroom',
            'content' => '<p>Course content.</p>',
            'type' => 'online',
            'location_id' => null,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $startsAt->copy()->addHours(2)->toDateTimeString(),
            'publish_at' => now()->subDay()->toDateTimeString(),
            'closes_at' => $startsAt->copy()->subDay()->toDateTimeString(),
            'status' => 'open',
            'is_private' => 0,
            'is_hidden' => 0,
            'hero_media_name' => $heroName,
            'registration' => 'classroom',
            'price' => 'Free',
            'max_tickets' => 12,
        ]);

        $workshop = Workshop::query()->where('title', 'Microbit Term 1 Classroom')->firstOrFail();

        $response->assertRedirect(route('admin.workshop.index'));
        $this->assertSame('classroom', (string) $workshop->registration);
        $this->assertNotNull($workshop->classSession);
        $this->assertSame((string) $workshop->classSession->slug, (string) $workshop->ticket_group_slug);
        $this->assertDatabaseHas('class_sessions', [
            'id' => $workshop->class_session_id,
            'access_group_slug' => (string) $workshop->classSession->slug,
        ]);

        $this->actingAs($admin)
            ->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSeeText('Enrol Now')
            ->assertDontSee(route('class.show', $workshop->classSession), false);
    }

    public function test_workshop_create_validation_keeps_the_selected_hero_image(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $startsAt = now()->addDays(7);

        $this->from(route('admin.workshop.create'))
            ->followingRedirects()
            ->actingAs($admin)
            ->post(route('admin.workshop.store'), [
                'title' => 'Image Retention Workshop',
                'content' => '',
                'type' => 'online',
                'location_id' => null,
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->addHours(2)->toDateTimeString(),
                'publish_at' => now()->subDay()->toDateTimeString(),
                'closes_at' => $startsAt->copy()->subDay()->toDateTimeString(),
                'status' => 'open',
                'is_private' => 0,
                'is_hidden' => 0,
                'hero_media_name' => $heroName,
                'registration' => 'none',
            ])
            ->assertOk()
            ->assertSeeText('Item content is required')
            ->assertSee('value="'.$heroName.'"', false);
    }

    public function test_admin_can_link_a_workshop_to_an_existing_classroom(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $startsAt = now()->addDays(7);
        $forumCategory = ForumCategory::query()->create([
            'name' => 'Microbit Term 1 Forum',
            'slug' => 'microbit-term-1-forum',
            'read_group_slug' => 'microbit-t1-2026',
            'write_group_slug' => 'microbit-t1-2026',
        ]);
        $classSession = ClassSession::query()->create([
            'title' => 'Microbit Term 1 Classroom',
            'forum_category_id' => $forumCategory->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ]);

        $this->actingAs($admin)->post(route('admin.workshop.store'), [
            'title' => 'Microbit Term 1 Access',
            'content' => '<p>Course content.</p>',
            'type' => 'online',
            'location_id' => null,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $startsAt->copy()->addHours(2)->toDateTimeString(),
            'publish_at' => now()->subDay()->toDateTimeString(),
            'closes_at' => $startsAt->copy()->subDay()->toDateTimeString(),
            'status' => 'open',
            'is_private' => 0,
            'is_hidden' => 0,
            'hero_media_name' => $heroName,
            'registration' => 'classroom',
            'price' => 'Free',
            'max_tickets' => 12,
            'class_session_id' => $classSession->id,
        ])->assertRedirect(route('admin.workshop.index'));

        $workshop = Workshop::query()->where('title', 'Microbit Term 1 Access')->firstOrFail();

        $this->assertSame((string) $classSession->id, (string) $workshop->class_session_id);
        $this->assertDatabaseCount('class_sessions', 1);
        $this->assertDatabaseHas('class_sessions', [
            'id' => $classSession->id,
            'workshop_id' => (string) $workshop->id,
        ]);

        $this->actingAs($admin)
            ->get(route('class.show', $classSession))
            ->assertOk()
            ->assertSeeText('Course')
            ->assertSeeText('Forum');

        $this->actingAs($admin)
            ->get(route('forum.category.show', $forumCategory->slug))
            ->assertOk()
            ->assertSeeText('Course');
    }

    public function test_classroom_workshop_public_pages_show_course_schedule_summary_and_course_date_fallback(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $firstStart = now()->addDays(2)->setTime(17, 30);
        $secondStart = $firstStart->copy()->addWeek();
        $classSession = ClassSession::query()->create([
            'title' => 'Weekly Classroom',
            'starts_at' => $firstStart,
            'ends_at' => $secondStart->copy()->addHour(),
            'broadcast_sessions_json' => [
                [
                    'starts_at' => $firstStart->toDateTimeString(),
                    'ends_at' => $firstStart->copy()->addHour()->toDateTimeString(),
                    'label' => 'Week 1',
                ],
                [
                    'starts_at' => $secondStart->toDateTimeString(),
                    'ends_at' => $secondStart->copy()->addHour()->toDateTimeString(),
                    'label' => 'Week 2',
                ],
            ],
        ]);
        $workshop = Workshop::query()->create([
            'title' => 'Weekly Classroom Workshop',
            'content' => '<p>Course content.</p>',
            'starts_at' => $firstStart,
            'ends_at' => $secondStart->copy()->addHour(),
            'publish_at' => now()->subDay(),
            'closes_at' => $firstStart->copy()->subDay(),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'classroom',
            'max_tickets' => 12,
            'class_session_id' => $classSession->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $indexResponse = $this->get(route('workshop.index'));
        $indexResponse->assertOk()
            ->assertSeeText($workshop->title)
            ->assertSeeText('weekly')
            ->assertSeeText($workshop->courseScheduleFirstStartLabel());

        $showResponse = $this->get(route('workshop.show', $workshop));
        $showResponse->assertOk()
            ->assertSeeText('This course streams weekly.')
            ->assertSeeText($workshop->courseScheduleDisplayLines()[0])
            ->assertSeeText($workshop->courseScheduleDisplayLines()[1]);

        $anytimeClassSession = ClassSession::query()->create([
            'title' => 'Anytime Classroom',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
        ]);
        $anytimeWorkshop = Workshop::query()->create([
            'title' => 'Anytime Classroom Workshop',
            'content' => '<p>Course content.</p>',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(3),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'classroom',
            'max_tickets' => 12,
            'class_session_id' => $anytimeClassSession->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $this->get(route('workshop.show', $anytimeWorkshop))
            ->assertOk()
            ->assertSeeText($anytimeWorkshop->courseScheduleDisplayLines()[0])
            ->assertDontSeeText('This course streams weekly.');
    }

    public function test_admin_workshop_edit_disables_course_schedule_fields_when_a_course_is_linked(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $firstStart = now()->addDays(3)->setTime(16, 0);
        $classSession = ClassSession::query()->create([
            'title' => 'Linked Classroom',
            'starts_at' => $firstStart,
            'ends_at' => $firstStart->copy()->addHour(),
            'broadcast_sessions_json' => [
                [
                    'starts_at' => $firstStart->toDateTimeString(),
                    'ends_at' => $firstStart->copy()->addHour()->toDateTimeString(),
                    'label' => 'Week 1',
                ],
            ],
        ]);
        $workshop = Workshop::query()->create([
            'title' => 'Linked Classroom Workshop',
            'content' => '<p>Course content.</p>',
            'starts_at' => $firstStart,
            'ends_at' => $firstStart->copy()->addHour(),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(2),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'classroom',
            'max_tickets' => 12,
            'class_session_id' => $classSession->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.edit', $workshop))
            ->assertOk()
            ->assertSeeText('These fields are set automatically based on the linked course information.')
            ->assertSee('x-bind:disabled="isCourseManaged()"', false)
            ->assertSee('x-model="selectedClassSessionId"', false);
    }

    public function test_classroom_checkout_invites_missing_accounts_and_assigns_the_access_group(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        $heroName = $this->createHeroMedia($owner);
        $startsAt = now()->addDays(7);
        $classSession = ClassSession::query()->create([
            'title' => 'Microbit Term 1 Classroom',
            'term_number' => 1,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ]);

        $workshop = Workshop::query()->create([
            'title' => 'Microbit Term 1 Classroom',
            'content' => '<p>Course content.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subDay(),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'classroom',
            'max_tickets' => 3,
            'class_session_id' => $classSession->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $response = $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Casey',
            'surname' => 'Parent',
            'email' => 'casey.parent@example.com',
            'phone' => '0400000000',
        ]);

        $response->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $user = User::query()->whereRaw('LOWER(email) = ?', ['casey.parent@example.com'])->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('user_groups', [
            'user_id' => (string) $user->id,
            'slug' => (string) $classSession->slug,
        ]);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->mailable instanceof UserRegister
                && $job->to === 'casey.parent@example.com';
        });
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

    private function createHeroMedia(User $owner): string
    {
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1200,
            'user_id' => (string) $owner->id,
        ]);

        return $heroName;
    }
}
