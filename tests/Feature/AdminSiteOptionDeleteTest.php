<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Models\User;
use App\Services\Finance\DocumentTermsFooter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSiteOptionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_admin_can_delete_a_custom_setting_without_changing_other_settings(): void
    {
        $this->admin();
        $custom = SiteOption::query()->create(['name' => 'custom.example', 'value' => 'Custom value']);
        SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Shared terms']);
        SiteOption::query()->create(['name' => 'custom.other', 'value' => 'Other value']);
        $this->assertSame('Custom value', SiteOption::value('custom.example'));

        $this->deleteJson(route('admin.site_option.destroy', $custom))
            ->assertOk()->assertJson(['success' => true, 'redirect' => route('admin.site_option.index')]);

        $this->assertDatabaseMissing('site_options', ['id' => $custom->id]);
        $this->assertNull(SiteOption::value('custom.example'));
        $this->assertDatabaseHas('site_options', ['name' => 'document.footer.terms', 'value' => 'Shared terms']);
        $this->assertDatabaseHas('site_options', ['name' => 'custom.other', 'value' => 'Other value']);
    }

    public function test_deleting_a_blank_document_override_restores_shared_terms(): void
    {
        $this->admin();
        SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Shared terms']);
        $override = SiteOption::query()->create(['name' => 'document.footer.terms.invoice', 'value' => null]);
        $this->assertSame('', DocumentTermsFooter::render('invoice'));

        $this->deleteJson(route('admin.site_option.destroy', $override))->assertOk();

        $this->assertSame('Shared terms', DocumentTermsFooter::render('invoice'));
    }

    public function test_builtin_settings_cannot_be_deleted_even_with_a_direct_request(): void
    {
        $this->admin();
        $setting = SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Existing custom wording']);

        $this->deleteJson(route('admin.site_option.destroy', $setting))->assertUnprocessable();

        $this->assertDatabaseHas('site_options', ['id' => $setting->id, 'value' => 'Existing custom wording']);
    }

    public function test_non_admin_cannot_delete_settings(): void
    {
        $this->actingAs(User::factory()->create());
        $setting = SiteOption::query()->create(['name' => 'custom.example', 'value' => 'Keep this']);

        $this->deleteJson(route('admin.site_option.destroy', $setting))->assertForbidden();

        $this->assertDatabaseHas('site_options', ['id' => $setting->id]);
    }

    public function test_guest_cannot_delete_settings(): void
    {
        $setting = SiteOption::query()->create(['name' => 'custom.example', 'value' => 'Keep this']);

        $this->deleteJson(route('admin.site_option.destroy', $setting))->assertRedirect(route('login'));

        $this->assertDatabaseHas('site_options', ['id' => $setting->id]);
    }

    public function test_browser_delete_redirects_to_the_settings_list_with_a_success_message(): void
    {
        $this->admin();
        $setting = SiteOption::query()->create(['name' => 'custom.example', 'value' => 'Delete this']);

        $this->delete(route('admin.site_option.destroy', $setting))
            ->assertRedirect(route('admin.site_option.index'))
            ->assertSessionHas('message', 'Site option has been deleted');

        $this->assertDatabaseMissing('site_options', ['id' => $setting->id]);
    }

    public function test_custom_settings_show_delete_and_builtin_settings_show_restore(): void
    {
        $this->admin();
        $custom = SiteOption::query()->create(['name' => 'document.footer.terms.invoice', 'value' => 'Invoice terms']);
        $builtin = SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Shared terms']);

        $this->get(route('admin.site_option.index', ['search' => 'document.footer.terms']))
            ->assertOk()
            ->assertSee('data-delete-option="'.$custom->id.'"', false)
            ->assertDontSee('data-reset-option="'.$custom->id.'"', false)
            ->assertDontSee('data-delete-option="'.$builtin->id.'"', false)
            ->assertSee('data-reset-option="'.$builtin->id.'"', false);

        $this->get(route('admin.site_option.edit', $custom))->assertOk()->assertSee('data-editor-delete', false);
        $this->get(route('admin.site_option.edit', $builtin))->assertOk()->assertDontSee('data-editor-delete', false);
        $this->get(route('admin.site_option.create'))->assertOk()->assertDontSee('data-editor-delete', false);
    }
}
