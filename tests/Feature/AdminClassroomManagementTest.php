<?php

namespace Tests\Feature;

use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_duplicate_and_delete_classrooms(): void
    {
        $admin = $this->createAdminUser();
        $teacher = User::factory()->create([
            'username' => 'teacher.one',
            'email' => 'teacher.one@example.com',
        ]);
        $student = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);
        $heroMedia = Media::query()->create([
            'name' => 'microbit-term-1-hero.png',
            'title' => 'Microbit Term 1 Hero',
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $sessionStart = Carbon::create(2026, 3, 29, 15, 44, 0);
        $sessionEnd = (clone $sessionStart)->addHours(2);

        $response = $this->actingAs($admin)->post(route('admin.course.store'), [
            'title' => 'Microbit Term 1',
            'slug' => '',
            'room_name' => '',
            'hero_media_name' => $heroMedia->name,
            'summary' => 'Intro classroom',
            'instructions_html' => '<p>Welcome</p>',
            'live_chat_enabled' => 1,
            'forum_category_choice' => 'create',
            'forum_category_name' => 'Microbit Term 1 Forum',
            'broadcast_sessions_json' => json_encode([
                [
                    'starts_at' => $sessionStart->format('d/m/Y, h:i a'),
                    'ends_at' => $sessionEnd->format('d/m/Y, h:i a'),
                    'label' => 'Session 1',
                ],
            ], JSON_THROW_ON_ERROR),
            'teacher_identifiers' => $teacher->email,
            'student_identifiers' => $student->username,
        ]);

        $classSession = ClassSession::query()->where('title', 'Microbit Term 1')->firstOrFail();
        $response->assertRedirect(route('admin.course.edit', $classSession));

        $this->assertSame('Microbit Term 1', $classSession->title);
        $this->assertNotEmpty($classSession->slug);
        $this->assertMatchesRegularExpression('/^class-.+-term\d+-\d{4}$/', $classSession->slug);
        $this->assertSame($classSession->slug, $classSession->access_group_slug);
        $this->assertSame($classSession->slug, $classSession->room_name);
        $this->assertSame($heroMedia->name, $classSession->hero_media_name);
        $this->assertTrue($classSession->live_chat_enabled);
        $this->assertSame($classSession->currentTermNumber(), $classSession->term_number);
        $this->assertCount(1, $classSession->broadcastSchedule());
        $this->assertSame(
            $sessionStart->format('Y-m-d\TH:i'),
            $classSession->broadcastSchedule()[0]['starts_at']
        );
        $this->assertSame(
            $sessionEnd->format('Y-m-d\TH:i'),
            $classSession->broadcastSchedule()[0]['ends_at']
        );
        $this->assertNotNull($classSession->forumCategory);
        $this->assertSame('Microbit Term 1 Forum', $classSession->forumCategory->name);
        $this->assertSame($classSession->slug, $classSession->forumCategory->read_group_slug);
        $this->assertSame($classSession->slug, $classSession->forumCategory->write_group_slug);
        UserGroup::query()->create([
            'user_id' => (string) $student->id,
            'slug' => $classSession->slug,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $student->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.course.edit', $classSession))
            ->assertOk()
            ->assertSeeText($classSession->title)
            ->assertSeeText('Hero image')
            ->assertSeeText('Course notes')
            ->assertSeeText('Enrolments')
            ->assertSeeText('Deleting this course will remove student access.')
            ->assertSeeText('1 Enroled student will lose access if you delete this course.')
            ->assertSeeText('The linked forum category will remain unless it is deleted separately.')
            ->assertDontSeeText('Teacher identifiers')
            ->assertDontSeeText('Access group')
            ->assertDontSeeText('Room name');

        $duplicateResponse = $this->actingAs($admin)->get(route('admin.course.create', [
            'duplicate_from' => $classSession->id,
        ]));

        $duplicateResponse
            ->assertOk()
            ->assertSeeText('Hero image')
            ->assertSeeText('Course notes')
            ->assertSeeText('Enrolments')
            ->assertDontSeeText('Teacher identifiers')
            ->assertDontSeeText('Access group')
            ->assertDontSeeText('Room name');

        $originalSlug = $classSession->slug;
        $originalRoomName = $classSession->room_name;

        $this->actingAs($admin)
            ->put(route('admin.course.update', $classSession), [
                'title' => 'Microbit Term 1 Updated',
            'slug' => 'microbit-term-1-updated',
            'room_name' => $classSession->room_name,
            'hero_media_name' => $heroMedia->name,
            'forum_category_choice' => (string) $classSession->forum_category_id,
            'forum_category_name' => 'Microbit Term 1 Forum Updated',
                'summary' => 'Updated classroom',
                'instructions_html' => '<p>Updated instructions</p>',
                'live_chat_enabled' => 0,
                'teacher_identifiers' => $student->email,
                'student_identifiers' => $teacher->email,
            ])
            ->assertRedirect(route('admin.course.edit', ['classSession' => 'microbit-term-1-updated']));

        $classSession->refresh();
        $this->assertSame('Microbit Term 1 Updated', $classSession->title);
        $this->assertSame('microbit-term-1-updated', $classSession->slug);
        $this->assertSame($classSession->slug, $classSession->access_group_slug);
        $this->assertSame($originalRoomName, $classSession->room_name);
        $this->assertSame($heroMedia->name, $classSession->hero_media_name);
        $this->assertFalse($classSession->live_chat_enabled);
        $this->assertSame($classSession->currentTermNumber(), $classSession->term_number);
        $this->assertSame('Microbit Term 1 Forum', $classSession->forumCategory?->name);
        $this->assertSame($classSession->slug, $classSession->forumCategory?->read_group_slug);
        $this->assertSame($classSession->slug, $classSession->forumCategory?->write_group_slug);

        $this->assertDatabaseMissing('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $teacher->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $student->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $classSession->id,
            'user_id' => $teacher->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);
        $this->assertDatabaseHas('user_groups', [
            'user_id' => $student->id,
            'slug' => $classSession->slug,
        ]);
        $this->assertDatabaseMissing('user_groups', [
            'user_id' => $student->id,
            'slug' => $originalSlug,
        ]);

        $duplicateActionResponse = $this->actingAs($admin)->get(route('admin.course.duplicate', $classSession));
        $duplicateActionResponse->assertRedirect();

        $duplicated = ClassSession::query()->where('duplicated_from_class_session_id', $classSession->id)->latest('created_at')->firstOrFail();
        $this->assertSame($classSession->title.' (copy)', $duplicated->title);
        $this->assertNull($duplicated->forum_category_id);
        $this->assertNull($duplicated->workshop_id);
        $this->assertDatabaseHas('class_enrolments', [
            'class_session_id' => $duplicated->id,
            'user_id' => $student->id,
            'role' => ClassEnrolment::ROLE_TEACHER,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.course.destroy', $classSession))
            ->assertRedirect(route('admin.course.index'));

        $this->assertDatabaseMissing('class_sessions', [
            'id' => $classSession->id,
        ]);
    }

    public function test_admin_cannot_save_course_streams_outside_the_course_window(): void
    {
        $admin = $this->createAdminUser();
        $heroMedia = Media::query()->create([
            'name' => 'microbit-term-2-hero.png',
            'title' => 'Microbit Term 2 Hero',
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $sessionStart = Carbon::create(2026, 6, 1, 9, 0, 0);
        $sessionEnd = (clone $sessionStart)->addHours(3);
        $streamStart = (clone $sessionEnd)->addHour();
        $streamEnd = (clone $streamStart)->addHour();

        $this->actingAs($admin)
            ->post(route('admin.course.store'), [
                'title' => 'Microbit Term 2',
                'slug' => '',
                'room_name' => '',
                'hero_media_name' => $heroMedia->name,
                'summary' => 'Intro classroom',
                'instructions_html' => '<p>Welcome</p>',
                'live_chat_enabled' => 1,
                'forum_category_choice' => 'create',
                'forum_category_name' => 'Microbit Term 2 Forum',
                'starts_at' => $sessionStart->format('Y-m-d H:i:s'),
                'ends_at' => $sessionEnd->format('Y-m-d H:i:s'),
                'broadcast_sessions_json' => json_encode([
                    [
                        'starts_at' => $streamStart->format('d/m/Y, h:i a'),
                        'ends_at' => $streamEnd->format('d/m/Y, h:i a'),
                        'label' => 'Session 1',
                    ],
                ], JSON_THROW_ON_ERROR),
                'teacher_identifiers' => '',
                'student_identifiers' => '',
            ])
            ->assertSessionHasErrors([
                'broadcast_sessions_json' => 'Live stream times must fall within the course start and end dates.',
            ]);

        $this->assertDatabaseMissing('class_sessions', [
            'title' => 'Microbit Term 2',
        ]);
    }

    public function test_admin_classroom_edit_page_marks_paid_students_and_admin_added_students(): void
    {
        $admin = $this->createAdminUser();
        $workshopOwner = User::factory()->create();
        Media::query()->create([
            'name' => 'workshop-linked-course-hero.png',
            'title' => 'Workshop Linked Course Hero',
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $workshopOwner->id,
        ]);
        $paidStudent = User::factory()->create([
            'username' => 'paid.student',
            'email' => 'paid.student@example.com',
        ]);
        $manualStudent = User::factory()->create([
            'username' => 'manual.student',
            'email' => 'manual.student@example.com',
        ]);
        $startsAt = Carbon::create(2026, 4, 1, 10, 0, 0);

        $workshop = Workshop::query()->create([
            'title' => 'Workshop-linked Course',
            'content' => '<p>Course content</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now(),
            'status' => 'open',
            'price' => '25.00',
            'registration' => 'classroom',
            'user_id' => $workshopOwner->id,
            'hero_media_name' => 'workshop-linked-course-hero.png',
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PAID,
            'user_id' => $paidStudent->id,
            'workshop_id' => $workshop->id,
            'firstname' => 'Paid',
            'surname' => 'Student',
            'email' => $paidStudent->email,
            'phone' => '',
        ]);

        $classSession = ClassSession::query()->create([
            'title' => 'Workshop-linked Course',
            'slug' => 'workshop-linked-course',
            'room_name' => 'workshop-linked-course',
            'hero_media_name' => 'workshop-linked-course-hero.png',
            'workshop_id' => $workshop->id,
            'created_by_user_id' => $admin->id,
        ]);

        $classSession->enrolments()->create([
            'user_id' => $paidStudent->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);
        $classSession->enrolments()->create([
            'user_id' => $manualStudent->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.course.edit', $classSession))
            ->assertOk()
            ->assertSeeText('Paid via workshop purchase')
            ->assertSeeText('Added by admin')
            ->assertSeeText('2 Enroled students will lose access if you delete this course.')
            ->assertSeeText('1 of those students paid through a workshop purchase.');
    }

    public function test_non_admin_cannot_access_classroom_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.course.index'))
            ->assertForbidden();
    }

    public function test_admin_classroom_index_lists_existing_classrooms(): void
    {
        $admin = $this->createAdminUser();
        $classSession = ClassSession::query()->create([
            'title' => 'Existing classroom',
            'slug' => 'existing-classroom',
            'room_name' => 'existing-classroom-room',
            'created_by_user_id' => $admin->id,
            'summary' => 'Existing classroom summary',
            'live_chat_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.course.index'))
            ->assertOk()
            ->assertSeeText('Courses')
            ->assertSeeText($classSession->title)
            ->assertSee(route('admin.course.edit', $classSession), false)
            ->assertSee(route('class.show', $classSession), false);
    }

    public function test_admin_classroom_index_shows_status_students_and_schedule(): void
    {
        $admin = $this->createAdminUser();
        $now = Carbon::create(2026, 4, 1, 12, 0, 0);
        Carbon::setTestNow($now);

        try {
            $pending = ClassSession::query()->create([
                'title' => 'Pending classroom',
                'slug' => 'pending-classroom',
                'room_name' => 'pending-classroom-room',
                'created_by_user_id' => $admin->id,
                'starts_at' => $now->copy()->addDay(),
                'ends_at' => $now->copy()->addDays(2),
            ]);

            $active = ClassSession::query()->create([
                'title' => 'Active classroom',
                'slug' => 'active-classroom',
                'room_name' => 'active-classroom-room',
                'created_by_user_id' => $admin->id,
                'starts_at' => $now->copy()->subDay(),
                'ends_at' => $now->copy()->addDay(),
            ]);

            $ended = ClassSession::query()->create([
                'title' => 'Ended classroom',
                'slug' => 'ended-classroom',
                'room_name' => 'ended-classroom-room',
                'created_by_user_id' => $admin->id,
                'starts_at' => $now->copy()->subDays(3),
                'ends_at' => $now->copy()->subDay(),
            ]);

            $this->actingAs($admin)
                ->get(route('admin.course.index'))
                ->assertOk()
                ->assertSeeText('Status')
                ->assertSeeText('Pending')
                ->assertSeeText('Active')
                ->assertSeeText('Ended')
                ->assertSeeText($pending->starts_at?->format('j M Y g:i a').' - '.$pending->ends_at?->format('j M Y g:i a'))
                ->assertSeeText($active->starts_at?->format('j M Y g:i a').' - '.$active->ends_at?->format('j M Y g:i a'))
                ->assertSeeText($ended->starts_at?->format('j M Y g:i a').' - '.$ended->ends_at?->format('j M Y g:i a'));
        } finally {
            Carbon::setTestNow();
        }
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
}
