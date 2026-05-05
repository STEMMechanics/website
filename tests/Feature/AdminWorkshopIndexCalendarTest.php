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
        $draftWorkshop = $this->createWorkshop('Draft workshop', $monthStart->copy()->addDays(2));
        $draftWorkshop->update(['status' => 'draft']);

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
        $response->assertSee('md:hidden');
        $response->assertSee('hidden overflow-x-auto rounded-xl border border-gray-200 bg-white md:block');
        $response->assertSee($monthStart->format('D j M'));
        $response->assertSee('title="Calendar PDF"', false);
        $response->assertSee('title="Pick Lists PDF"', false);
        $response->assertSee('title="Materials Summary PDF"', false);
        $response->assertSee('Choose workshop scope');
        $response->assertSee('All monthly workshops');
        $response->assertSee('Upcoming workshops');
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
        $draftWorkshop = $this->createWorkshop('Draft workshop', $monthStart->copy()->addDays(2));
        $draftWorkshop->update(['status' => 'draft']);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = $this->runPdfInfo($tempFile);
        $pdfText = $this->extractPdfText($tempFile);
        @unlink($tempFile);

        $this->assertIsString($pdfInfo);
        $this->assertMatchesRegularExpression('/^Pages:\s+1$/m', $pdfInfo);
        $this->assertStringNotContainsString('Draft workshop', $pdfText);
    }

    public function test_admin_workshop_month_pick_lists_pdf_route_streams_a_multipage_pdf(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);
        $template = $this->createPickListTemplate();

        $this->createWorkshopWithPickList('Pick list workshop one', $monthStart, $template, 4);
        $this->createWorkshopWithPickList('Pick list workshop two', $monthStart->copy()->addDays(1), $template, 6);
        $draftWorkshop = $this->createWorkshop('Draft workshop', $monthStart->copy()->addDays(2));
        $draftWorkshop->update(['status' => 'draft']);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.pick-lists.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-pick-lists-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = $this->runPdfInfo($tempFile);
        $pdfText = $this->extractPdfText($tempFile);
        @unlink($tempFile);

        $this->assertIsString($pdfInfo);
        $this->assertMatchesRegularExpression('/^Pages:\s+\d+$/m', $pdfInfo);
        preg_match('/^Pages:\s+(\d+)$/m', $pdfInfo, $matches);
        $this->assertGreaterThanOrEqual(2, (int) ($matches[1] ?? 0));
        $this->assertStringNotContainsString('Draft workshop', $pdfText);
    }

    public function test_admin_workshop_month_materials_pdf_route_streams_a_summary_pdf(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);
        $template = $this->createPickListTemplate();

        $this->createWorkshopWithPickList('Pick list workshop one', $monthStart, $template, 4);
        $this->createWorkshopWithPickList('Pick list workshop two', $monthStart->copy()->addDays(1), $template, 6);
        $this->createWorkshopWithPickList('Pick list workshop three', $monthStart->copy()->addDays(2), $template, 2);
        $draftWorkshop = $this->createWorkshop('Draft workshop', $monthStart->copy()->addDays(3));
        $draftWorkshop->update(['status' => 'draft']);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.materials.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-materials-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = $this->runPdfInfo($tempFile);
        $pdfText = $this->extractPdfText($tempFile);
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
        $this->assertStringNotContainsString('Draft workshop', $pdfText);
    }

    public function test_admin_workshop_month_materials_pdf_route_can_filter_to_upcoming_workshops(): void
    {
        $admin = $this->createAdminUser();
        $fixedNow = \Illuminate\Support\Carbon::create(2026, 5, 15, 12, 0, 0, config('app.timezone'));
        \Illuminate\Support\Carbon::setTestNow($fixedNow);

        try {
            $template = $this->createPickListTemplate();

            $this->createWorkshopWithPickList('Past workshop', $fixedNow->copy()->subDay(), $template, 4);
            $this->createWorkshopWithPickList('Upcoming workshop', $fixedNow->copy()->addDay(), $template, 6);

            $response = $this->actingAs($admin)->get(route('admin.workshop.month.materials.pdf', [
                'month' => $fixedNow->format('Y-m'),
                'materials_scope' => 'upcoming',
            ]));

            $response->assertOk();
            $content = $response->getContent();
            $this->assertStringStartsWith('%PDF', $content);

            $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-materials-pdf-');
            $this->assertNotFalse($tempFile);

            file_put_contents($tempFile, $content);
            $pdfText = $this->extractPdfText($tempFile);
            @unlink($tempFile);

            $this->assertIsString($pdfText);
            $this->assertMatchesRegularExpression('/Upcoming workshop/i', $pdfText);
            $this->assertStringNotContainsString('Past workshop', $pdfText);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_admin_workshop_month_materials_pdf_route_numbers_all_pages_in_a_multi_page_summary(): void
    {
        $admin = $this->createAdminUser();
        $monthStart = now()->startOfMonth()->addDays(10);
        $template = $this->createPickListTemplate(60);

        $this->createWorkshopWithPickList('Long materials workshop', $monthStart, $template, 12);

        $response = $this->actingAs($admin)->get(route('admin.workshop.month.materials.pdf', [
            'month' => $monthStart->format('Y-m'),
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        $tempFile = tempnam(sys_get_temp_dir(), 'workshop-month-materials-pdf-');
        $this->assertNotFalse($tempFile);

        file_put_contents($tempFile, $content);
        $pdfInfo = $this->runPdfInfo($tempFile);
        $pdfText = $this->extractPdfText($tempFile);
        @unlink($tempFile);

        $this->assertIsString($pdfInfo);
        $this->assertMatchesRegularExpression('/^Pages:\s+[2-9]\d*$/m', $pdfInfo);
        $this->assertIsString($pdfText);
        $this->assertMatchesRegularExpression('/Page 1 of \d+/m', $pdfText);
        $this->assertMatchesRegularExpression('/Page 2 of \d+/m', $pdfText);
        $this->assertStringNotContainsString('Page 1 of 1', $pdfText);
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

    private function createPickListTemplate(int $itemCount = 2): PickListTemplate
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

        for ($index = 3; $index <= $itemCount; $index++) {
            PickListTemplateItem::query()->create([
                'pick_list_template_id' => $template->id,
                'item_name' => sprintf('Additional material %02d', $index),
                'quantity_type' => PickListTemplateItem::TYPE_FIXED,
                'quantity_value' => 1,
                'sort_order' => $index * 10,
            ]);
        }

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

    private function runPdfInfo(string $tempFile): string
    {
        $binary = $this->resolveBinary('pdfinfo');
        if ($binary === null) {
            $this->markTestSkipped('pdfinfo is not available in this environment.');
        }

        return $this->runBinaryCommand([$binary, $tempFile]);
    }

    private function extractPdfText(string $tempFile): string
    {
        $binary = $this->resolveBinary('pdftotext');
        if ($binary === null) {
            $this->markTestSkipped('pdftotext is not available in this environment.');
        }

        return $this->runBinaryCommand([$binary, $tempFile, '-']);
    }

    /**
     * @param array<int, string> $commandParts
     */
    private function runBinaryCommand(array $commandParts): string
    {
        $output = [];
        $exitCode = 0;
        $command = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $commandParts)).' 2>/dev/null';

        exec($command, $output, $exitCode);

        if ($exitCode === 127) {
            $this->markTestSkipped('Required PDF inspection binary is not available in this environment.');
        }

        $this->assertSame(0, $exitCode, 'Command failed: '.$command);

        return implode("\n", $output);
    }

    private function resolveBinary(string $binary): ?string
    {
        foreach ([
            '/opt/homebrew/bin/'.$binary,
            '/usr/local/bin/'.$binary,
            '/usr/bin/'.$binary,
            '/bin/'.$binary,
            $binary,
        ] as $candidate) {
            if ($candidate === $binary) {
                return $candidate;
            }

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
