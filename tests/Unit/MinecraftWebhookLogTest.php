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

    public function test_troubleshooting_hint_recommends_reachability_checks_for_timeout_errors(): void
    {
        $log = new MinecraftWebhookLog([
            'target_url' => 'https://play.stemcraft.com.au:8125/stemcraft/webhook',
            'error_message' => 'cURL error 28: Connection timed out after 10001 milliseconds (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://play.stemcraft.com.au:8125/stemcraft/webhook',
        ]);

        $this->assertSame(
            'Check TCP reachability to play.stemcraft.com.au:8125 from the app server. If Laravel and the plugin share a host or private network, use an internal HTTP URL such as http://127.0.0.1:8125/stemcraft/webhook instead of a public hostname.',
            $log->troubleshootingHint()
        );
    }

    public function test_troubleshooting_hint_recommends_dns_checks_for_resolution_errors(): void
    {
        $log = new MinecraftWebhookLog([
            'target_url' => 'http://play.stemcraft.com.at:8125/stemcraft/webhook',
            'error_message' => 'cURL error 6: Could not resolve host: play.stemcraft.com.at (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for http://play.stemcraft.com.at:8125/stemcraft/webhook',
        ]);

        $this->assertSame(
            'Check DNS resolution for play.stemcraft.com.at:8125 from the app server and verify the configured webhook URL.',
            $log->troubleshootingHint()
        );
    }
}
