<?php

namespace Tests\Unit;

use App\Models\MinecraftWebhookLog;
use Tests\TestCase;

class MinecraftWebhookLogTest extends TestCase
{
    public function test_error_summary_removes_curl_noise_from_connection_errors(): void
    {
        $log = new MinecraftWebhookLog([
            'error_message' => 'cURL error 6: Could not resolve host: play.stemcraft.com.at (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for http://play.stemcraft.com.at/webhook',
        ]);

        $this->assertSame('Could not resolve host: play.stemcraft.com.at', $log->errorSummary());
    }
}
