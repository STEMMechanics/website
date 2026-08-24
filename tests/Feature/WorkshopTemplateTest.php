<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_editor_contains_scroll_containment_and_workshop_placeholder_help(): void
    {
        $admin = $this->createAdminUser();
        $template = PickListTemplate::query()->create(['name' => 'Social media template']);

        $this->actingAs($admin)
            ->get(route('admin.workshop-template.edit', $template))
            ->assertOk()
            ->assertSee('max-h-[calc(100dvh-2rem)]', false)
            ->assertSee('max-w-4xl', false)
            ->assertSee('h-[calc(100dvh-2rem)] max-w-none', false)
            ->assertSee('overscroll-contain', false)
            ->assertSee('x-on:click.self="closeTaskEditor()"', false)
            ->assertSee("document.body.classList.toggle('overflow-hidden'", false)
            ->assertSeeText('Details and Alerts')
            ->assertSeeText('Subtask content')
            ->assertSee('Expand task editor', false)
            ->assertSee('Show workshop placeholders', false)
            ->assertSeeText('$25.00 or Free')
            ->assertSee('min-h-80', false)
            ->assertSee('miniEditor', false)
            ->assertSeeText('{date-short}')
            ->assertSeeText('{date-long}')
            ->assertSeeText('{date-ddd dd/mm/yyyy}')
            ->assertSeeText('{start-time}')
            ->assertSeeText('{end-time}')
            ->assertSeeText('{location}')
            ->assertSeeText('{ages}')
            ->assertSeeText('{cost}');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_a_complete_workshop_template(): void
    {
        $admin = $this->createAdminUser();
        $attachment = $this->createMedia($admin, 'paper-speakers-guide.pdf');

        $response = $this->actingAs($admin)->post(route('admin.workshop-template.store'), [
            'name' => 'Paper Speakers - Standard',
            'description' => 'Standard paper speaker workshop.',
            'duration' => '1.5 hours',
            'participants' => '8-24',
            'run_sheet' => '<h2>Welcome</h2><p>Introduce the activity.</p>',
            'run_sheet_drawing_data' => 'data:image/png;base64,dGVzdA==',
            'tasks' => [
                [
                    'name' => 'Charge batteries',
                    'notes' => '<p><strong>The day before</strong></p>',
                    'subtasks' => json_encode([
                        ['title' => 'Facebook', 'content' => '<p>Schedule the Facebook post.</p>'],
                        ['title' => 'Instagram', 'content' => '<p>Prepare the Instagram caption.</p>'],
                    ], JSON_THROW_ON_ERROR),
                    'sort_order' => 20,
                ],
                ['name' => 'Print worksheets', 'notes' => null, 'sort_order' => 10],
            ],
            'items' => [
                [
                    'item_name' => 'Copper tape',
                    'quantity_type' => PickListTemplateItem::TYPE_PER_PARTICIPANT,
                    'quantity_value' => 1,
                    'sort_order' => 10,
                ],
            ],
            'attachments' => [$attachment->name],
        ]);

        $template = PickListTemplate::query()->where('name', 'Paper Speakers - Standard')->firstOrFail();

        $response->assertRedirect(route('admin.workshop-template.edit', $template));
        $this->assertSame('1.5 hours', $template->duration);
        $this->assertSame('8-24', $template->participants);
        $this->assertCount(2, $template->tasks);
        $this->assertSame(['Charge batteries', 'Print worksheets'], $template->tasks->pluck('name')->all());
        $this->assertSame('<p><strong>The day before</strong></p>', $template->tasks->first()->notes);
        $this->assertSame([
            ['title' => 'Facebook', 'content' => '<p>Schedule the Facebook post.</p>'],
            ['title' => 'Instagram', 'content' => '<p>Prepare the Instagram caption.</p>'],
        ], $template->tasks->first()->subtasks);
        $this->assertCount(1, $template->items);
        $this->assertSame([$attachment->name], $template->attachments()->pluck('media.name')->all());
    }

    public function test_duplicate_creates_an_independent_workshop_template_variant(): void
    {
        $admin = $this->createAdminUser();
        $attachment = $this->createMedia($admin, 'advanced-notes.pdf');
        $template = PickListTemplate::query()->create([
            'name' => 'Paper Speakers - Advanced',
            'description' => 'Advanced notes',
            'duration' => '2 hours',
            'participants' => '6-16',
            'run_sheet' => '<p>Advanced run sheet</p>',
        ]);
        $template->tasks()->create([
            'name' => 'Prepare soldering stations',
            'subtasks' => [['title' => 'Safety', 'content' => '<p>Check each station.</p>']],
            'sort_order' => 10,
        ]);
        $template->items()->create([
            'item_name' => 'Soldering iron',
            'quantity_type' => PickListTemplateItem::TYPE_FIXED,
            'quantity_value' => 4,
            'sort_order' => 10,
        ]);
        $template->updateFiles([$attachment->name], PickListTemplate::ATTACHMENT_COLLECTION);

        $response = $this->actingAs($admin)->post(route('admin.workshop-template.duplicate', $template));
        $copy = PickListTemplate::query()->where('name', 'Paper Speakers - Advanced (Copy)')->firstOrFail();

        $response->assertRedirect(route('admin.workshop-template.edit', $copy));
        $this->assertNotSame($template->id, $copy->id);
        $this->assertSame('2 hours', $copy->duration);
        $this->assertSame(['Prepare soldering stations'], $copy->tasks->pluck('name')->all());
        $this->assertSame([['title' => 'Safety', 'content' => '<p>Check each station.</p>']], $copy->tasks->first()->subtasks);
        $this->assertSame(['Soldering iron'], $copy->items->pluck('item_name')->all());
        $this->assertSame([$attachment->name], $copy->attachments()->pluck('media.name')->all());
    }

    public function test_attachment_can_be_uploaded_directly_and_template_pdf_is_available(): void
    {
        Storage::fake('archive');
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->post(route('admin.workshop-template.store'), [
            'name' => 'Paper Speakers - Drop In',
            'duration' => '2 hours',
            'participants' => '10-20',
            'attachment_uploads' => [UploadedFile::fake()->create('facilitator-guide.pdf', 120, 'application/pdf')],
        ]);

        $template = PickListTemplate::query()->where('name', 'Paper Speakers - Drop In')->firstOrFail();
        $response->assertRedirect(route('admin.workshop-template.edit', $template));
        $this->assertSame(['facilitator-guide.pdf'], $template->attachments()->pluck('media.name')->all());

        $pdfResponse = $this->actingAs($admin)->get(route('admin.workshop-template.pdf', $template));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }

    private function createMedia(User $owner, string $name): Media
    {
        return Media::query()->create([
            'name' => $name,
            'title' => $name,
            'hash' => str_repeat('a', 64),
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);
    }
}
