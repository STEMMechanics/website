<?php

namespace Tests\Feature;

use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountClassroomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_menu_includes_classrooms_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee(route('account.classrooms.index'), false);
    }

    public function test_account_classrooms_page_lists_accessible_classrooms_and_hides_inaccessible_ones(): void
    {
        $user = User::factory()->create([
            'username' => 'student.one',
            'email' => 'student.one@example.com',
        ]);

        UserGroup::query()->create([
            'user_id' => (string) $user->id,
            'slug' => 'microbit-t1-2026',
        ]);

        $groupClass = ClassSession::query()->create([
            'title' => 'Microbit T1',
            'slug' => 'microbit-t1',
            'room_name' => 'microbit-t1',
            'access_group_slug' => 'microbit-t1-2026',
            'summary' => 'Group classroom',
            'starts_at' => now()->addDay(),
        ]);

        $enrolledClass = ClassSession::query()->create([
            'title' => 'Special Workshop',
            'slug' => 'special-workshop',
            'room_name' => 'special-workshop',
            'summary' => 'Enrolment classroom',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        ClassEnrolment::query()->create([
            'class_session_id' => $enrolledClass->id,
            'user_id' => $user->id,
            'role' => ClassEnrolment::ROLE_STUDENT,
        ]);

        ClassSession::query()->create([
            'title' => 'Hidden Classroom',
            'slug' => 'hidden-classroom',
            'room_name' => 'hidden-classroom',
            'access_group_slug' => 'different-group',
            'summary' => 'Should not be visible',
        ]);

        $response = $this->actingAs($user)->get(route('account.classrooms.index'));

        $response->assertOk();
        $response->assertSeeText('Classrooms');
        $response->assertSeeText('Microbit T1');
        $response->assertSeeText('Special Workshop');
        $response->assertDontSeeText('Hidden Classroom');
        $response->assertSee(route('class.show', $groupClass), false);
        $response->assertSee(route('class.show', $enrolledClass), false);
        $response->assertSeeText('Upcoming sessions');
        $response->assertSeeText('Current sessions');
    }
}
