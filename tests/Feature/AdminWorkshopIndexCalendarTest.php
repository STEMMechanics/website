<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminWorkshopIndexCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_workshop_index_defaults_to_the_list_view(): void
    {
        $admin = $this->createAdminUser();
        $startsAt = now()->addDays(10);

        $this->createWorkshop('Workshop list view', $startsAt);

        $response = $this->actingAs($admin)->get(route('admin.workshop.index'));

        $response->assertOk();
        $response->assertSee('Title');
        $response->assertSee('Workshop list view');
    }

    public function test_admin_workshop_index_month_view_groups_workshops_and_shows_month_navigation(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);
        $nextMonthStart = now()->startOfMonth()->addMonthNoOverflow()->addDays(10);

        $this->createWorkshop('Workshop this month', $monthStart);
        $this->createWorkshop('Workshop next month', $nextMonthStart);

        $response = $this->actingAs($admin)->get(route('admin.workshop.index', [
            'view' => 'month',
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee($monthStart->format('F Y'));
        $response->assertSeeInOrder(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
        $response->assertSee(route('admin.workshop.month.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));
        $response->assertSee(route('admin.workshop.month.pick-lists.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));
        $response->assertSee(route('admin.workshop.month.materials.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));
        $response->assertSee('title="Calendar PDF"', false);
        $response->assertSee('title="Pick Lists PDF"', false);
        $response->assertSee('title="Materials Summary PDF"', false);
        $response->assertSee('Workshop this month');
        $response->assertDontSee('Workshop next month');
        $response->assertSee('Previous month');
        $response->assertSee('Next month');
        $response->assertSee('List');
        $response->assertSee('Month');
    }

    public function test_admin_workshop_month_pdf_route_streams_a_pdf(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);

        $this->createWorkshop('Workshop this month', $monthStart);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = shell_exec('pdfinfo '.escapeshellarg($tempFile).' 2>/dev/null');
        @unlink($tempFile);

        $this->assertIsString($pdfInfo);
        $this->assertMatchesRegularExpression('/^Pages:\s+1$/m', $pdfInfo);
    }

    public function test_admin_workshop_month_pick_lists_pdf_route_streams_a_multipage_pdf(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);
        $template = $this->createPickListTemplate();

        $this->createWorkshopWithPickList('Pick list workshop one', $monthStart, $template, 4);
        $this->createWorkshopWithPickList('Pick list workshop two', $monthStart->copy()->addDays(1), $template, 6);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.pick-lists.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-pick-lists-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = shell_exec('pdfinfo '.escapeshellarg($tempFile).' 2>/dev/null');
        @unlink($tempFile);

        $this->assertIsString($pdfInfo);
        $this->assertMatchesRegularExpression('/^Pages:\s+\d+$/m', $pdfInfo);
        preg_match('/^Pages:\s+(\d+)$/m', $pdfInfo, $matches);
        $this->assertGreaterThanOrEqual(2, (int) ($matches[1] ?? 0));
    }

    public function test_admin_workshop_month_materials_pdf_route_streams_a_summary_pdf(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);
        $template = $this->createPickListTemplate();

        $this->createWorkshopWithPickList('Pick list workshop one', $monthStart, $template, 4);
        $this->createWorkshopWithPickList('Pick list workshop two', $monthStart->copy()->addDays(1), $template, 6);
        $this->createWorkshopWithPickList('Pick list workshop three', $monthStart->copy()->addDays(2), $template, 2);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.materials.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-materials-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = shell_exec('pdfinfo '.escapeshellarg($tempFile).' 2>/dev/null');
        $pdfText = shell_exec('pdftotext '.escapeshellarg($tempFile).' - 2>/dev/null');
        @unlink($tempFile);

        $this->assertIsString($pdfInfo);
        $this->assertMatchesRegularExpression('/^Pages:\s+1$/m', $pdfInfo);
        $this->assertIsString($pdfText);
        $this->assertStringContainsString('Workshop Materials Summary', $pdfText);
        $this->assertStringContainsString($monthStart->format('F Y'), $pdfText);
        $this->assertStringContainsString('Page 1 of 1', $pdfText);
        $this->assertMatchesRegularExpression('/Pick list workshop one/i', $pdfText);
        $this->assertMatchesRegularExpression('/Pick list workshop two/i', $pdfText);
        $this->assertMatchesRegularExpression('/Pick list workshop three/i', $pdfText);
        $this->assertMatchesRegularExpression('/Battery pack/i', $pdfText);
        $this->assertMatchesRegularExpression('/Toolkit/i', $pdfText);
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

    private function createWorkshop(string $title, \Illuminate\Support\Carbon $startsAt): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => $title,
            'content' => '<p>Workshop content</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subDay(),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }

    private function createPickListTemplate(): PickListTemplate
    {
        $template = PickListTemplate::query()->create([
            'name' => 'Month Export Template',
            'description' => 'Pack the following items.',
        ]);

        PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Battery pack',
            'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 2,
            'sort_order' => 10,
        ]);

        PickListTemplateItem::query()->create([
            'pick_list_template_id' => $template->id,
            'item_name' => 'Toolkit',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 3,
            'sort_order' => 20,
        ]);

        return $template;
    }

    private function createWorkshopWithPickList(string $title, \Illuminate\Support\Carbon $startsAt, PickListTemplate $template, int $participants): Workshop
    {
        $workshop = $this->createWorkshop($title, $startsAt);

        $workshop->update([
            'pick_list_template_id' => $template->id,
            'pick_list_participants' => $participants,
            'pick_list_is_customized' => false,
            'pick_list_custom_items' => null,
            'pick_list_notes' => null,
        ]);

        return $workshop->refresh();
    }
}
