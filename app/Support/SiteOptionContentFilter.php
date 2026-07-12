<?php

namespace App\Support;

use App\Contracts\ContentFilter;
use App\Models\SiteOption;
use Blaspsoft\Blasp\Core\Result;
use Blaspsoft\Blasp\Facades\Blasp;
use Blaspsoft\Blasp\Enums\Severity;
use Illuminate\Support\Facades\Schema;

class SiteOptionContentFilter implements ContentFilter
{
    public function inspect(string $content, string $context = 'default'): ContentFilterResult
    {
        return $this->inspectWithSettings($content, $context);
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    public function inspectWithSettings(string $content, string $context = 'default', array $settings = []): ContentFilterResult
    {
        if (! $this->isEnabled($settings)) {
            return ContentFilterResult::allow();
        }

        $plainText = $this->plainText($content);
        if ($plainText === '') {
            return ContentFilterResult::allow();
        }

        if ($this->containsProfanity($plainText, $settings)) {
            return ContentFilterResult::block(
                'profanity',
                'Your message includes language that is not allowed. Please revise it and try again.'
            );
        }

        $matchingCustomPattern = $this->matchingCustomPattern($plainText, $settings);
        if ($matchingCustomPattern !== null) {
            return ContentFilterResult::block(
                'custom_pattern',
                'Your message includes language or patterns that are not allowed. Please revise it and try again.',
                $matchingCustomPattern,
            );
        }

        if ($this->containsAllCaps($plainText, $settings)) {
            return ContentFilterResult::block(
                'all_caps',
                'Your message looks like it is written in all caps. Please revise it and try again.'
            );
        }

        if ($this->containsRepeatedCharacters($plainText, $settings)) {
            return ContentFilterResult::block(
                'repeated_characters',
                'Your message includes repeated character patterns that are not allowed. Please revise it and try again.'
            );
        }

        if ($this->containsRepeatedWords($plainText, $settings)) {
            return ContentFilterResult::block(
                'repeated_words',
                'Your message includes repeated word patterns that are not allowed. Please revise it and try again.'
            );
        }

        return ContentFilterResult::allow();
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    public function profanityResult(string $content, array $settings = []): ?Result
    {
        $plainText = $this->plainText($content);
        if ($plainText === '') {
            return null;
        }

        $result = $this->blaspResult($plainText, $settings);
        return $result->isOffensive() ? $result : null;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    public function maskedProfanity(string $content, array $settings = []): ?string
    {
        $plainText = $this->plainText($content);
        if ($plainText === '') {
            return null;
        }

        $result = $this->profanityResult($content, $settings);
        if ($result === null) {
            return null;
        }

        $masked = trim($result->clean());

        if ($masked === '' || $masked === $plainText) {
            return null;
        }

        return $masked;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function isEnabled(array $settings = []): bool
    {
        return $this->optionBoolean('moderation.content-filter.enabled', true, $settings);
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function containsProfanity(string $plainText, array $settings = []): bool
    {
        $result = $this->blaspResult($plainText, $settings);

        if (! $result->isOffensive()) {
            return false;
        }

        $exceptions = $this->exceptionCanonicalMap($settings);
        if ($exceptions === []) {
            return true;
        }

        foreach ($result->words() as $profanity) {
            $capturedWord = $this->canonicalizeToken($profanity->text);
            $resolvedWord = $this->canonicalizeToken($profanity->base);

            if (
                ($capturedWord !== '' && isset($exceptions[$capturedWord]))
                || ($resolvedWord !== '' && isset($exceptions[$resolvedWord]))
            ) {
                continue;
            }

            if ($resolvedWord !== '' || $capturedWord !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function containsAllCaps(string $plainText, array $settings = []): bool
    {
        if (! $this->optionBoolean('moderation.content-filter.block-all-caps', true, $settings)) {
            return false;
        }

        preg_match_all('/\p{Lu}/u', $plainText, $upperMatches);
        preg_match_all('/\p{Ll}/u', $plainText, $lowerMatches);

        return count($upperMatches[0]) >= $this->optionInteger('moderation.content-filter.min-all-caps-letters', 12, $settings)
            && count($lowerMatches[0]) === 0;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function containsRepeatedCharacters(string $plainText, array $settings = []): bool
    {
        $maxRun = $this->optionInteger('moderation.content-filter.max-repeated-character-run', 6, $settings);
        if ($maxRun < 2) {
            return false;
        }

        return preg_match('/([[:alnum:]])\1{'.max(1, $maxRun - 1).',}/iu', $plainText) === 1;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function containsRepeatedWords(string $plainText, array $settings = []): bool
    {
        $maxRun = $this->optionInteger('moderation.content-filter.max-repeated-word-run', 4, $settings);
        if ($maxRun < 2) {
            return false;
        }

        return preg_match('/\b([\p{L}\p{N}]{2,})\b(?:[\s\W]+\1\b){'.max(1, $maxRun - 1).',}/iu', $plainText) === 1;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function matchingCustomPattern(string $plainText, array $settings = []): ?string
    {
        foreach ($this->customPatterns($settings) as $pattern) {
            if (@preg_match((string) $pattern['compiled'], $plainText) === 1) {
                return (string) $pattern['raw'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function optionValue(string $name, string $default = '', array $settings = []): string
    {
        if (array_key_exists($name, $settings)) {
            return trim((string) ($settings[$name] ?? $default));
        }

        $fallback = SiteOption::defaultValue($name) ?? $default;
        if (! Schema::hasTable('site_options')) {
            return (string) $fallback;
        }

        return (string) (SiteOption::value($name, (string) $fallback) ?? $fallback);
    }

    /**
     * @param  array<int, string>|string  $values
     * @return array<int, string>
     */
    private function normalizedList(array|string $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/\r\n|\r|\n/', $values) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $values
        ), static fn (string $value) => $value !== '')));
    }

    /**
     * @param  array<string, scalar|null>  $settings
     * @return array<string, true>
     */
    private function exceptionCanonicalMap(array $settings = []): array
    {
        $map = [];

        foreach ($this->normalizedList($this->optionValue('moderation.content-filter.exception-words', '', $settings)) as $value) {
            $canonical = $this->canonicalizeToken($value);
            if ($canonical !== '') {
                $map[$canonical] = true;
            }
        }

        return $map;
    }

    private function canonicalizeToken(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    private function optionBoolean(string $name, bool $default = false, array $settings = []): bool
    {
        return in_array(
            strtolower(trim($this->optionValue($name, $default ? '1' : '0', $settings))),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function blaspResult(string $plainText, array $settings = []): Result
    {
        $minimumSeverity = $this->minimumSeverity($settings);

        return $minimumSeverity !== null
            ? Blasp::withSeverity($minimumSeverity)->check($plainText)
            : Blasp::check($plainText);
    }

    /**
     * @param  array<string, scalar|null>  $settings
     */
    private function minimumSeverity(array $settings = []): ?Severity
    {
        $value = strtolower(trim($this->optionValue('moderation.content-filter.minimum-severity', '', $settings)));
        if ($value === '') {
            return null;
        }

        return Severity::tryFrom($value);
    }

    private function optionInteger(string $name, int $default, array $settings = []): int
    {
        $raw = trim($this->optionValue($name, (string) $default, $settings));

        return is_numeric($raw) ? (int) $raw : $default;
    }

    /**
     * @return array<int, array{raw: string, compiled: string}>
     */
    private function customPatterns(array $settings = []): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $this->optionValue('moderation.content-filter.custom-patterns', '', $settings)) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->map(fn ($line) => [
                'raw' => $line,
                'compiled' => '~'.$line.'~iu',
            ])
            ->values()
            ->all();
    }

    private function plainText(string $content): string
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
