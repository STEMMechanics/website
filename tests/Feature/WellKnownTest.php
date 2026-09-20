<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WellKnownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('well_known');
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    public function test_admin_can_manage_existing_files_and_preserve_uploaded_bytes(): void
    {
        $this->admin();
        $disk = Storage::disk('well_known');
        $name = 'apple-developer-merchantid-domain-association';
        $disk->put($name, 'existing');
        $this->get(route('admin.well-known.index'))->assertOk()->assertSee($name);
        $this->get(route('admin.well-known.edit', $name))->assertOk()->assertSee('existing');
        $content = "  signed-verification\r\nno-final-newline";
        $this->put(route('admin.well-known.update', $name), ['mode' => 'upload', 'document' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertRedirect(route('admin.well-known.edit', $name))->assertSessionHasNoErrors();
        $this->assertSame($content, $disk->get($name));
        $this->delete(route('admin.well-known.destroy', $name))->assertRedirect(route('admin.well-known.index'));
        $disk->assertMissing($name);
    }

    public function test_text_is_saved_without_trimming_or_adding_newlines_and_existing_files_require_editing(): void
    {
        $this->admin();
        $content = "  Contact: mailto:security@example.com\n  ";
        $this->post(route('admin.well-known.store'), ['filename' => 'security.txt', 'mode' => 'text', 'verification_text' => $content])->assertSessionHasNoErrors();
        $this->assertSame($content, Storage::disk('well_known')->get('security.txt'));
        $this->post(route('admin.well-known.store'), ['filename' => 'security.txt', 'mode' => 'text', 'verification_text' => 'replace'])->assertSessionHasErrors('filename');
        $this->assertSame($content, Storage::disk('well_known')->get('security.txt'));
    }

    public function test_non_admins_cannot_read_or_change_verification_files(): void
    {
        $this->get(route('admin.well-known.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.well-known.index'))->assertForbidden();
        $this->post(route('admin.well-known.store'), ['filename' => 'example', 'mode' => 'text', 'verification_text' => 'test'])->assertForbidden();
        $this->put(route('admin.well-known.update', 'example'), ['mode' => 'text', 'verification_text' => 'test'])->assertForbidden();
        $this->delete(route('admin.well-known.destroy', 'example'))->assertForbidden();
    }

    public function test_paths_executable_names_oversized_uploads_and_symlinks_are_rejected(): void
    {
        $this->admin();
        foreach (['../outside.txt', '.htaccess', 'code.php', 'code.php.txt', 'code.PHP', 'nested/file.txt', 'file.svg', 'file.html', 'nested\\file.txt'] as $name) {
            $this->post(route('admin.well-known.store'), ['filename' => $name, 'mode' => 'text', 'verification_text' => '<?php echo "test";'])->assertSessionHasErrors('filename');
        }
        $this->post(route('admin.well-known.store'), ['filename' => 'oversized', 'mode' => 'upload', 'document' => UploadedFile::fake()->create('oversized', 1025)])->assertSessionHasErrors('document');
        $disk = Storage::disk('well_known');
        $disk->put('original.txt', 'preserve');
        symlink($disk->path('original.txt'), $disk->path('linked.txt'));
        $this->put(route('admin.well-known.update', 'linked.txt'), ['mode' => 'text', 'verification_text' => 'changed'])->assertNotFound();
        $this->assertSame('preserve', $disk->get('original.txt'));
    }
}
