<?php

namespace App\Http\Controllers;

use App\Models\SiteOption;
use App\Services\MinecraftMessageModerationService;
use App\Support\SiteOptionContentFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForumModerationController extends Controller
{
    public function __construct(
        private readonly SiteOptionContentFilter $contentFilter,
        private readonly MinecraftMessageModerationService $minecraftMessageModerationService,
    ) {}

    /**
     * @var array<string, string>
     */
    private const OPTION_DEFAULTS = [
        'moderation.content-filter.enabled' => '1',
        'moderation.content-filter.custom-patterns' => '',
        'moderation.content-filter.exception-words' => '',
        'moderation.content-filter.minimum-severity' => 'mild',
        'moderation.content-filter.profanity-mask-character' => '*',
        'moderation.content-filter.blocked-message-placeholder' => '[Message blocked by moderation filter]',
        'moderation.content-filter.block-all-caps' => '1',
        'moderation.content-filter.min-all-caps-letters' => '12',
        'moderation.content-filter.max-repeated-character-run' => '6',
        'moderation.content-filter.max-repeated-word-run' => '4',
        'minecraft.message-failure-notification-delay-minutes' => '20',
    ];

    public function show(): View
    {
        SiteOption::ensureDefaultOptionsExist();

        return view('admin.forum.moderation', [
            'settings' => [
                'enabled' => $this->optionValue('moderation.content-filter.enabled', '1') === '1',
                'custom_patterns' => $this->optionValue('moderation.content-filter.custom-patterns'),
                'exception_words' => $this->optionValue('moderation.content-filter.exception-words'),
                'minimum_severity' => $this->optionValue('moderation.content-filter.minimum-severity', 'mild'),
                'profanity_mask_character' => $this->optionValue('moderation.content-filter.profanity-mask-character', '*'),
                'blocked_message_placeholder' => $this->optionValue('moderation.content-filter.blocked-message-placeholder', '[Message blocked by moderation filter]'),
                'block_all_caps' => $this->optionValue('moderation.content-filter.block-all-caps', '1') === '1',
                'min_all_caps_letters' => $this->optionValue('moderation.content-filter.min-all-caps-letters', '12'),
                'max_repeated_character_run' => $this->optionValue('moderation.content-filter.max-repeated-character-run', '6'),
                'max_repeated_word_run' => $this->optionValue('moderation.content-filter.max-repeated-word-run', '4'),
                'message_failure_notification_delay_minutes' => $this->optionValue('minecraft.message-failure-notification-delay-minutes', '20'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $this->validateSettings($request);

        $compiledPatterns = $this->validateCustomPatterns((string) ($validated['custom_patterns'] ?? ''));
        if ($compiledPatterns !== []) {
            // Validation already ensures the patterns compile cleanly; nothing else needed here.
        }

        $this->storeOption('moderation.content-filter.enabled', (string) $validated['enabled']);
        $this->storeOption('moderation.content-filter.custom-patterns', trim((string) ($validated['custom_patterns'] ?? '')));
        $this->storeOption('moderation.content-filter.exception-words', $this->normalizedExceptionWords((string) ($validated['exception_words'] ?? '')));
        $this->storeOption('moderation.content-filter.minimum-severity', strtolower(trim((string) ($validated['minimum_severity'] ?? 'mild'))));
        $this->storeOption('moderation.content-filter.profanity-mask-character', $this->normalizedMaskCharacter((string) ($validated['profanity_mask_character'] ?? '*')));
        $this->storeOption('moderation.content-filter.blocked-message-placeholder', trim((string) ($validated['blocked_message_placeholder'] ?? '')) ?: '[Message blocked by moderation filter]');
        $this->storeOption('moderation.content-filter.block-all-caps', (string) $validated['block_all_caps']);
        $this->storeOption('moderation.content-filter.min-all-caps-letters', (string) $validated['min_all_caps_letters']);
        $this->storeOption('moderation.content-filter.max-repeated-character-run', (string) $validated['max_repeated_character_run']);
        $this->storeOption('moderation.content-filter.max-repeated-word-run', (string) $validated['max_repeated_word_run']);
        $this->storeOption('minecraft.message-failure-notification-delay-minutes', (string) $validated['message_failure_notification_delay_minutes']);

        session()->flash('message', 'Discussion moderation settings have been updated.');
        session()->flash('message-title', 'Moderation updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.forum.moderation.show');
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateSettings($request, [
            'test_content' => ['required', 'string', 'max:10000'],
        ]);

        $this->validateCustomPatterns((string) ($validated['custom_patterns'] ?? ''));

        $settings = $this->settingsFromValidated($validated);
        $previewMinimumSeverity = strtolower(trim((string) ($validated['preview_minimum_severity'] ?? '')));
        if ($previewMinimumSeverity !== '') {
            $settings['moderation.content-filter.minimum-severity'] = $previewMinimumSeverity;
        }

        $result = $this->contentFilter->inspectWithSettings(
            (string) $validated['test_content'],
            'forum',
            $settings,
        );
        $profanityResult = $result->blocked && $result->rule === 'profanity'
            ? $this->contentFilter->profanityResult((string) $validated['test_content'], $settings)
            : null;

        return response()->json([
            'blocked' => $result->blocked,
            'rule' => $result->rule,
            'rule_label' => $this->ruleLabel($result->rule),
            'message' => $result->message,
            'detail' => $result->detail,
            'applied_minimum_severity' => $settings['moderation.content-filter.minimum-severity'] ?? null,
            'filtered_message' => $profanityResult !== null ? trim($profanityResult->clean()) : null,
            'profanity_severity' => $profanityResult?->severity()?->value,
            'profanity_score' => $profanityResult?->score(),
            'profanity_words' => $profanityResult?->words()->map(fn ($word) => $word->toArray())->all(),
            'blocked_message_placeholder' => trim((string) ($validated['blocked_message_placeholder'] ?? '')) !== ''
                ? trim((string) $validated['blocked_message_placeholder'])
                : $this->minecraftMessageModerationService->blockedPlaceholder(),
        ]);
    }

    /**
     * @param  array<string, array<int, string>>  $extraRules
     * @return array<string, mixed>
     */
    private function validateSettings(Request $request, array $extraRules = []): array
    {
        return $request->validate(array_merge([
            'enabled' => ['required', 'in:0,1'],
            'custom_patterns' => ['nullable', 'string'],
            'exception_words' => ['nullable', 'string'],
            'minimum_severity' => ['nullable', 'in:mild,moderate,high,extreme'],
            'profanity_mask_character' => ['required', 'string', 'max:10'],
            'blocked_message_placeholder' => ['required', 'string', 'max:255'],
            'block_all_caps' => ['required', 'in:0,1'],
            'min_all_caps_letters' => ['required', 'integer', 'min:1', 'max:100'],
            'max_repeated_character_run' => ['required', 'integer', 'min:2', 'max:100'],
            'max_repeated_word_run' => ['required', 'integer', 'min:2', 'max:100'],
            'message_failure_notification_delay_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'preview_minimum_severity' => ['nullable', 'in:mild,moderate,high,extreme'],
        ], $extraRules));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, scalar|null>
     */
    private function settingsFromValidated(array $validated): array
    {
        return [
            'moderation.content-filter.enabled' => (string) $validated['enabled'],
            'moderation.content-filter.custom-patterns' => trim((string) ($validated['custom_patterns'] ?? '')),
            'moderation.content-filter.exception-words' => $this->normalizedExceptionWords((string) ($validated['exception_words'] ?? '')),
            'moderation.content-filter.minimum-severity' => strtolower(trim((string) ($validated['minimum_severity'] ?? 'mild'))),
            'moderation.content-filter.profanity-mask-character' => $this->normalizedMaskCharacter((string) ($validated['profanity_mask_character'] ?? '*')),
            'moderation.content-filter.blocked-message-placeholder' => trim((string) ($validated['blocked_message_placeholder'] ?? '')) ?: '[Message blocked by moderation filter]',
            'moderation.content-filter.block-all-caps' => (string) $validated['block_all_caps'],
            'moderation.content-filter.min-all-caps-letters' => (string) $validated['min_all_caps_letters'],
            'moderation.content-filter.max-repeated-character-run' => (string) $validated['max_repeated_character_run'],
            'moderation.content-filter.max-repeated-word-run' => (string) $validated['max_repeated_word_run'],
            'minecraft.message-failure-notification-delay-minutes' => (string) $validated['message_failure_notification_delay_minutes'],
        ];
    }

    private function ruleLabel(?string $rule): ?string
    {
        return match ($rule) {
            'profanity' => 'Blasp profanity filter',
            'custom_pattern' => 'Custom regex pattern',
            'all_caps' => 'All-caps rule',
            'repeated_characters' => 'Repeated characters rule',
            'repeated_words' => 'Repeated words rule',
            default => null,
        };
    }

    private function optionValue(string $name, ?string $fallback = null): string
    {
        return (string) SiteOption::value($name, $fallback ?? (self::OPTION_DEFAULTS[$name] ?? ''));
    }

    private function storeOption(string $name, string $value): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value]
        );
    }

    private function normalizedMaskCharacter(string $value): string
    {
        $trimmed = trim($value);

        return mb_substr($trimmed !== '' ? $trimmed : '*', 0, 1);
    }

    private function normalizedExceptionWords(string $value): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim(html_entity_decode((string) $line, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ->filter()
            ->values()
            ->implode(PHP_EOL);
    }

    /**
     * @return array<int, string>
     */
    private function validateCustomPatterns(string $raw): array
    {
        $patterns = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $compiled = [];

        foreach ($patterns as $index => $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            $regex = '~'.$pattern.'~iu';
            if (@preg_match($regex, '') === false) {
                throw ValidationException::withMessages([
                    'custom_patterns' => 'Pattern on line '.($index + 1).' is not a valid regular expression.',
                ]);
            }

            $compiled[] = $regex;
        }

        return $compiled;
    }
}
