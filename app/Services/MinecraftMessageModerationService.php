<?php

namespace App\Services;

use App\Models\SiteOption;
use App\Support\ForumContent;
use App\Support\MinecraftMessageModerationResult;
use App\Support\SiteOptionContentFilter;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Schema;

class MinecraftMessageModerationService
{
    public const DEFAULT_MASK_CHARACTER = '*';

    public const DEFAULT_BLOCKED_PLACEHOLDER = '[Message blocked by moderation filter]';

    public function __construct(
        private readonly SiteOptionContentFilter $contentFilter,
    ) {}

    public function inspect(string $content): MinecraftMessageModerationResult
    {
        $result = $this->contentFilter->inspect($content, 'forum');
        if (! $result->blocked) {
            return MinecraftMessageModerationResult::allow();
        }

        return MinecraftMessageModerationResult::block(
            reason: $this->normalizeReason($result->rule),
            reasonLabel: $this->ruleLabel($result->rule),
            reasonDetail: $result->detail,
            filteredMessage: $result->rule === 'profanity' ? $this->maskedProfanity($content) : null,
        );
    }

    public function ruleLabel(?string $rule): ?string
    {
        return match ($rule) {
            'profanity' => 'Blasp profanity filter',
            'custom_pattern', 'custom_regex' => 'Custom regex pattern',
            'all_caps' => 'All-caps rule',
            'repeated_characters' => 'Repeated characters rule',
            'repeated_words' => 'Repeated words rule',
            default => null,
        };
    }

    public function blockedPlaceholder(): string
    {
        $placeholder = trim($this->optionValue(
            'moderation.content-filter.blocked-message-placeholder',
            self::DEFAULT_BLOCKED_PLACEHOLDER,
        ));

        return $placeholder !== '' ? $placeholder : self::DEFAULT_BLOCKED_PLACEHOLDER;
    }

    public function maskCharacter(): string
    {
        $character = trim($this->optionValue(
            'moderation.content-filter.profanity-mask-character',
            self::DEFAULT_MASK_CHARACTER,
        ));

        return mb_substr($character !== '' ? $character : self::DEFAULT_MASK_CHARACTER, 0, 1);
    }

    public function failureSummary(?string $filteredMessage, ?string $reason, ?string $reasonDetail): string
    {
        $filteredMessage = trim((string) $filteredMessage);
        if ($filteredMessage !== '') {
            return $filteredMessage;
        }

        $label = $this->ruleLabel($reason) ?? 'Blocked';
        if ($reason === 'custom_regex' && trim((string) $reasonDetail) !== '') {
            return $label.': '.trim((string) $reasonDetail);
        }

        return $label;
    }

    private function maskedProfanity(string $content): ?string
    {
        $plainText = ForumContent::plainText($content);
        if ($plainText === '') {
            return null;
        }

        $masked = Blasp::maskWith($this->maskCharacter())->check($plainText)->getCleanString();
        $masked = trim((string) $masked);

        if ($masked === '' || $masked === $plainText) {
            return null;
        }

        return $masked;
    }

    private function normalizeReason(?string $rule): ?string
    {
        return match ($rule) {
            'custom_pattern' => 'custom_regex',
            default => $rule,
        };
    }

    private function optionValue(string $name, string $default = ''): string
    {
        $fallback = SiteOption::defaultValue($name) ?? $default;
        if (! Schema::hasTable('site_options')) {
            return (string) $fallback;
        }

        return (string) (SiteOption::value($name, (string) $fallback) ?? $fallback);
    }
}
