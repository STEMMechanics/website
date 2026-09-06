<?php

namespace Tests\Feature;

use App\Mail\SiteErrorAlert;
use App\Services\SiteErrorNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SiteErrorNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reported_site_errors_send_an_alert_to_the_admin_address(): void
    {
        Mail::fake();
        config(['app.env' => 'production', 'security.error_recipients' => 'admin@stemmechanics.com.au']);

        app(SiteErrorNotificationService::class)->notify(new \RuntimeException('Broken circuit'), Request::create('https://example.com/login?token=secret&signature=private'));

        Mail::assertSent(SiteErrorAlert::class);

        $mail = Mail::sent(SiteErrorAlert::class)->first();
        $this->assertNotNull($mail);
        $this->assertArrayNotHasKey('requestUrl', $mail->context);
        $this->assertNotEmpty($mail->context['errorId']);
        $this->assertStringNotContainsString('Broken circuit', $mail->render());
        $this->assertStringNotContainsString('secret', $mail->render());
        $this->assertTrue($mail->hasTo('admin@stemmechanics.com.au'));
    }
}
