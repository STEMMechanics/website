<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CspScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_scripts_have_a_matching_fresh_nonce_and_local_dialog_asset(): void
    {
        $first = $this->get('/')->assertOk();
        $nonce = $this->assertScriptPolicy($first);
        $this->assertNotSame($nonce, $this->assertScriptPolicy($this->get('/')->assertOk()));
        $first->assertDontSee('cdn.jsdelivr.net/npm/sweetalert2', false)
            ->assertDontSee('onload=', false);
        $this->assertMatchesRegularExpression('~src="[^\"]*/build/assets/sweetalert2[^\"]*\.js"~', $first->getContent());
    }

    public function test_expense_scripts_and_money_inputs_do_not_require_unsafe_inline(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.expense.create'))->assertOk();
        $this->assertScriptPolicy($response);
        $response->assertSee('data-money-format', false)->assertDontSee('onblur=', false);
    }

    private function assertScriptPolicy($response): string
    {
        $policy = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertMatchesRegularExpression("/'nonce-([A-Za-z0-9]+)'/", $policy);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy, $matches);
        $nonce = $matches[1];
        $this->assertGreaterThanOrEqual(32, strlen($nonce));
        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $scripts = $document->getElementsByTagName('script');
        $this->assertGreaterThan(0, $scripts->length);
        foreach ($scripts as $script) {
            $this->assertSame($nonce, $script->getAttribute('nonce'));
        }

        return $nonce;
    }
}
