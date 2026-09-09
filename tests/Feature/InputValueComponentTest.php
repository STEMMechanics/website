<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class InputValueComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        request()->setLaravelSession(app('session.store'));
    }

    public function test_unnamed_disabled_fields_keep_their_value_even_after_unrelated_validation_errors(): void
    {
        foreach ([[], ['billing_city' => 'Brisbane', 'billing_postcode' => 'invalid']] as $old) {
            session()->flashInput($old);
            $errors = (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['billing_postcode' => 'Invalid postcode']));
            view()->share('errors', $errors);
            $html = Blade::render('<x-ui.input label="Country" value="Australia" disabled />', compact('errors'));
            $this->assertStringContainsString('value="Australia"', $html);
            $this->assertStringContainsString('disabled', $html);
            $this->assertStringNotContainsString('Invalid postcode', $html);
            $this->assertStringNotContainsString('Brisbane', $html);
        }
    }

    public function test_named_fields_still_restore_their_own_old_input(): void
    {
        session()->flashInput(['billing_city' => 'Brisbane']);
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-ui.input name="billing_city" label="City" value="Sydney" />');
        $this->assertStringContainsString('value="Brisbane"', $html);
    }
}
