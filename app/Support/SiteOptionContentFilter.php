<?php

namespace App\Support;

use App\Contracts\ContentFilter;
use App\Models\SiteOption;
use Blaspsoft\Blasp\Config\ConfigurationLoader;
use Blaspsoft\Blasp\Facades\Blasp;
use Illuminate\Support\Facades\Schema;

class SiteOptionContentFilter implements ContentFilter
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $defaultFalsePositivesCache = [];

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

        $plainText = ForumContent::plainText($content);
        if ($plainText === '') {
            return ContentFilterResult::allow();
        }

        if ($this->containsProfanity($plainText, $settings)) {
            return ContentFilterResult::block(
                'profanity',
                'Your post includes language that is not allowed in this discussion space. Please revise it and try again.'
            );
        }

        $matchingCustomPattern = $this->matchingCustomPattern($plainText, $settings);
        if ($matchingCustomPattern !== null) {
            return ContentFilterResult::block(
                'custom_pattern',
                'Your post includes language or patterns that are not allowed in this discussion space. Please revise it and try again.',
                $matchingCustomPattern,
            );
        }

        if ($this->containsAllCaps($plainText, $settings)) {
            return ContentFilterResult::block(
                'all_caps',
                'Your post looks like it is written in all caps. Please revise it and try again.'
            );
        }

        if ($this->containsRepeatedCharacters($plainText, $settings)) {
            return ContentFilterResult::block(
                'repeated_characters',
                'Your post includes repeated character patterns that are not allowed. Please revise it and try again.'
            );
        }

        if ($this->containsRepeatedWords($plainText, $settings)) {
            return ContentFilterResult::block(
                'repeated_words',
                'Your post includes repeated word patterns that are not allowed. Please revise it and try again.'
            );
        }

        return ContentFilterResult::allow();
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
        $falsePositives = $this->falsePositives($settings);

        if ($falsePositives === []) {
            return Blasp::check($plainText)->hasProfanity();
        }

        return Blasp::configure(null, $falsePositives)->check($plainText)->hasProfanity();
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
     * @param  array<string, scalar|null>  $settings
     * @return array<int, string>
     */
    private function falsePositives(array $settings = []): array
    {
        $customFalsePositives = $this->normalizedList(
            $this->optionValue('moderation.content-filter.exception-words', '', $settings)
        );

        if ($customFalsePositives === []) {
            return [];
        }

        $language = (string) config('blasp.default_language', 'english');
        $defaultFalsePositives = $this->defaultFalsePositives($language);

        return array_values(array_unique(array_merge($defaultFalsePositives, $customFalsePositives)));
    }

    /**
     * @return array<int, string>
     */
    private function defaultFalsePositives(string $language): array
    {
        if (array_key_exists($language, $this->defaultFalsePositivesCache)) {
            return $this->defaultFalsePositivesCache[$language];
        }

        try {
            $config = (new ConfigurationLoader())->load(null, null, $language);
            $falsePositives = $config->getFalsePositives();
        } catch (\Throwable) {
            $falsePositives = config('blasp.false_positives', []);
        }

        $this->defaultFalsePositivesCache[$language] = $this->normalizedList($falsePositives);

        return $this->defaultFalsePositivesCache[$language];
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
            static fn ($value) => trim((string) $value),
            $values
        ), static fn (string $value) => $value !== '')));
    }

    private function optionBoolean(string $name, bool $default = false, array $settings = []): bool
    {
        return in_array(
            strtolower(trim($this->optionValue($name, $default ? '1' : '0', $settings))),
            ['1', 'true', 'yes', 'on'],
            true
        );
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
}
