<?php

namespace Tests\Feature;

use App\Http\Controllers\ForumModerationController;
use Tests\TestCase;
use ReflectionMethod;

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

    public function test_preview_endpoint_honours_minimum_severity_threshold(): void
    {
        $response = $this->postJson(route('admin.forum.moderation.preview'), [
            'enabled' => '1',
            'custom_patterns' => '',
            'minimum_severity' => 'extreme',
            'profanity_mask_character' => '*',
            'blocked_message_placeholder' => '[Message blocked by moderation filter]',
            'block_all_caps' => '1',
            'min_all_caps_letters' => '12',
            'max_repeated_character_run' => '6',
            'max_repeated_word_run' => '4',
            'message_failure_notification_delay_minutes' => '20',
            'test_content' => 'This includes fucking directly.',
        ]);

        $response->assertOk()->assertJson([
            'blocked' => false,
            'rule' => null,
            'rule_label' => null,
        ]);
    }

    public function test_preview_endpoint_can_override_minimum_severity_for_testing(): void
    {
        $response = $this->postJson(route('admin.forum.moderation.preview'), [
            'enabled' => '1',
            'custom_patterns' => '',
            'minimum_severity' => 'mild',
            'preview_minimum_severity' => 'moderate',
            'profanity_mask_character' => '*',
            'blocked_message_placeholder' => '[Message blocked by moderation filter]',
            'block_all_caps' => '1',
            'min_all_caps_letters' => '12',
            'max_repeated_character_run' => '6',
            'max_repeated_word_run' => '4',
            'message_failure_notification_delay_minutes' => '20',
            'test_content' => 'This includes damn directly.',
        ]);

        $response->assertOk()->assertJson([
            'blocked' => false,
            'rule' => null,
            'rule_label' => null,
            'applied_minimum_severity' => 'moderate',
        ]);
    }

    public function test_preview_endpoint_reports_profanity_details_for_f49(): void
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
            'test_content' => 'This includes F:49 directly.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'blocked' => true,
            'rule' => 'profanity',
            'rule_label' => 'Blasp profanity filter',
            'profanity_severity' => 'extreme',
            'profanity_score' => 62,
            'applied_minimum_severity' => 'mild',
        ]);
        $response->assertJsonPath('profanity_words.0.base', 'fag');
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

    public function test_exception_words_normalize_html_entity_apostrophes_before_storage(): void
    {
        $controller = app(ForumModerationController::class);
        $method = new ReflectionMethod($controller, 'normalizedExceptionWords');
        $method->setAccessible(true);

        $normalized = $method->invoke($controller, "t it&#039;s\nsnot");

        $this->assertSame("t it's\nsnot", $normalized);
    }

    public function test_preview_endpoint_allows_punctuation_variants_when_a_canonical_exception_matches(): void
    {
        $response = $this->postJson(route('admin.forum.moderation.preview'), [
            'enabled' => '1',
            'custom_patterns' => '',
            'exception_words' => "snot\n",
            'profanity_mask_character' => '*',
            'blocked_message_placeholder' => '[Message blocked by moderation filter]',
            'block_all_caps' => '1',
            'min_all_caps_letters' => '12',
            'max_repeated_character_run' => '6',
            'max_repeated_word_run' => '4',
            'message_failure_notification_delay_minutes' => '20',
            'test_content' => 'This contains s.not and should pass.',
        ]);

        $response->assertOk()->assertJson([
            'blocked' => false,
            'rule' => null,
            'rule_label' => null,
        ]);
    }
}
