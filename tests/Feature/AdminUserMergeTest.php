<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserBackupCode;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\UserMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserMergeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }

    public function test_merge_moves_user_records_unions_relationships_and_removes_source_login_data(): void
    {
        $this->createAdmin();
        $source = User::factory()->unverified()->create([
            'firstname' => 'Ghost',
            'surname' => 'Contact',
            'email' => null,
        ]);
        $destination = User::factory()->create([
            'firstname' => 'Real',
            'surname' => 'Account',
            'email' => 'real@example.com',
        ]);

        $sharedOrganisation = Organisation::factory()->create();
        $sourceOrganisation = Organisation::factory()->create();
        $source->update(['primary_organisation_id' => $sourceOrganisation->id]);
        $sharedOrganisation->contacts()->attach([$source->id, $destination->id]);
        $sourceOrganisation->contacts()->attach($source->id);

        UserGroup::query()->create(['user_id' => $source->id, 'slug' => 'shared']);
        UserGroup::query()->create(['user_id' => $destination->id, 'slug' => 'shared']);
        UserGroup::query()->create(['user_id' => $source->id, 'slug' => 'source-only']);

        $location = Location::factory()->create();
        $hero = Media::factory()->create(['user_id' => $source->id]);
        $workshop = Workshop::query()->create([
            'title' => 'Merged Workshop',
            'content' => '<p>Content</p>',
            'type' => Workshop::TYPE_PHYSICAL,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => 'tickets',
            'max_tickets' => 10,
            'location_id' => $location->id,
            'hero_media_name' => $hero->name,
            'user_id' => $source->id,
            'requested_by_user_id' => $source->id,
        ]);
        $ticket = Ticket::factory()->create(['workshop_id' => $workshop->id, 'user_id' => $source->id]);
        $quote = Quote::factory()->create(['user_id' => $source->id]);
        $invoice = Invoice::factory()->create(['user_id' => $source->id]);
        $payment = Payment::factory()->create(['user_id' => $source->id, 'created_by' => $source->id]);

        DB::table('workshop_interests')->insert([
            [
                'workshop_id' => $workshop->id,
                'user_id' => $source->id,
                'name' => 'Ghost Contact',
                'email' => 'old@example.com',
                'phone' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workshop_id' => $workshop->id,
                'user_id' => $destination->id,
                'name' => 'Real Account',
                'email' => 'real@example.com',
                'phone' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $source->tokens()->create([
            'type' => 'login',
            'data' => [],
            'expires_at' => now()->addHour(),
        ]);
        UserBackupCode::factory()->create(['user_id' => $source->id]);
        DB::table('sessions')->insert([
            'id' => 'source-session',
            'user_id' => $source->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        app(UserMergeService::class)->merge($source, $destination);

        $this->assertModelMissing($source);
        $destination->refresh();
        $this->assertSame('real@example.com', $destination->email);
        $this->assertSame((string) $sourceOrganisation->id, (string) $destination->primary_organisation_id);
        $this->assertSame((string) $destination->id, (string) $workshop->fresh()->user_id);
        $this->assertSame((string) $destination->id, (string) $workshop->fresh()->requested_by_user_id);
        $this->assertSame((string) $destination->id, (string) $hero->fresh()->user_id);
        $this->assertSame((string) $destination->id, (string) $ticket->fresh()->user_id);
        $this->assertSame((string) $destination->id, (string) $quote->fresh()->user_id);
        $this->assertSame((string) $destination->id, (string) $invoice->fresh()->user_id);
        $this->assertSame((string) $destination->id, (string) $payment->fresh()->user_id);
        $this->assertSame((string) $destination->id, (string) $payment->fresh()->created_by);

        $this->assertDatabaseHas('organisation_user', [
            'organisation_id' => $sourceOrganisation->id,
            'user_id' => $destination->id,
        ]);
        $this->assertDatabaseCount('organisation_user', 2);
        $this->assertDatabaseHas('user_groups', ['user_id' => $destination->id, 'slug' => 'shared']);
        $this->assertDatabaseHas('user_groups', ['user_id' => $destination->id, 'slug' => 'source-only']);
        $this->assertSame(2, DB::table('user_groups')->where('user_id', $destination->id)->count());
        $this->assertSame(1, DB::table('workshop_interests')->where('workshop_id', $workshop->id)->count());
        $this->assertDatabaseMissing('tokens', ['user_id' => $source->id]);
        $this->assertDatabaseMissing('user_backup_codes', ['user_id' => $source->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $source->id]);
    }

    public function test_admin_merge_endpoint_clearly_keeps_the_selected_destination(): void
    {
        $admin = $this->createAdmin();
        $source = User::factory()->create(['firstname' => 'Duplicate']);
        $destination = User::factory()->create([
            'firstname' => 'Surviving',
            'email' => 'survivor@example.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.user.edit', $source))
            ->assertOk()
            ->assertSee('Removed account')
            ->assertSee('Will be merged into')
            ->assertSee('Merge and Delete This User');

        $this->actingAs($admin)
            ->post(route('admin.user.merge', $source), [
                'target_user_id' => $destination->id,
                'confirm_merge' => 1,
            ])
            ->assertRedirect(route('admin.user.edit', $destination))
            ->assertSessionHas('message', 'Duplicate '.$source->surname.' has been merged into this account.');

        $this->assertModelMissing($source);
        $this->assertModelExists($destination);
    }

    public function test_admin_cannot_merge_their_current_account(): void
    {
        $admin = $this->createAdmin();
        $destination = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.user.merge', $admin), [
                'target_user_id' => $destination->id,
                'confirm_merge' => 1,
            ])
            ->assertSessionHasErrors('target_user_id');

        $this->assertModelExists($admin);
        $this->assertModelExists($destination);
    }
}
