<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForumModerationPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_preview_endpoint_reports_custom_regex_match_from_unsaved_settings(): void
    {
        $response = $this->postJson(route('admin.forum.moderation.preview'), [
            'enabled' => '1',
            'custom_patterns' => '\bfck\b',
            'profanity_mask_character' => '*',
            'blocked_message_placeholder' => '[Message blocked by moderation filter]',
            'block_all_caps' => '1',
            'min_all_caps_letters' => '12',
            'max_repeated_character_run' => '6',
            'max_repeated_word_run' => '4',
            'message_failure_notification_delay_minutes' => '20',
            'test_content' => 'This includes fck directly.',
        ]);

        $response->assertOk()->assertJson([
            'blocked' => true,
            'rule' => 'custom_pattern',
            'rule_label' => 'Custom regex pattern',
            'detail' => '\bfck\b',
        ]);
    }

    public function test_preview_endpoint_reports_allowed_content(): void
    {
        $response = $this->postJson(route('admin.forum.moderation.preview'), [
            'enabled' => '1',
            'custom_patterns' => '',
            'profanity_mask_character' => '*',
            'blocked_message_placeholder' => '[Message blocked by moderation filter]',
            'block_all_caps' => '1',
            'min_all_caps_letters' => '12',
            'max_repeated_character_run' => '6',
            'max_repeated_word_run' => '4',
            'message_failure_notification_delay_minutes' => '20',
            'test_content' => 'This is a normal sentence.',
        ]);

        $response->assertOk()->assertJson([
            'blocked' => false,
            'rule' => null,
            'rule_label' => null,
        ]);
    }

    public function test_preview_endpoint_honours_exception_words_from_unsaved_settings(): void
    {
        $response = $this->postJson(route('admin.forum.moderation.preview'), [
            'enabled' => '1',
            'custom_patterns' => '',
            'exception_words' => "fuck\n",
            'profanity_mask_character' => '*',
            'blocked_message_placeholder' => '[Message blocked by moderation filter]',
            'block_all_caps' => '1',
            'min_all_caps_letters' => '12',
            'max_repeated_character_run' => '6',
            'max_repeated_word_run' => '4',
            'message_failure_notification_delay_minutes' => '20',
            'test_content' => 'This includes fuck directly.',
        ]);

        $response->assertOk()->assertJson([
            'blocked' => false,
            'rule' => null,
            'rule_label' => null,
        ]);
    }
}
