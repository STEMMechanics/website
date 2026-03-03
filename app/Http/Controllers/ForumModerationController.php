<?php

namespace App\Http\Controllers;

use App\Support\SiteOptionContentFilter;
use App\Models\SiteOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForumModerationController extends Controller
{
    public function __construct(
        private readonly SiteOptionContentFilter $contentFilter,
    ) {
    }

    /**
     * @var array<string, string>
     */
    private const OPTION_DEFAULTS = [
        'moderation.content-filter.enabled' => '1',
        'moderation.content-filter.custom-patterns' => '',
        'moderation.content-filter.block-all-caps' => '1',
        'moderation.content-filter.min-all-caps-letters' => '12',
        'moderation.content-filter.max-repeated-character-run' => '6',
        'moderation.content-filter.max-repeated-word-run' => '4',
    ];

    public function show(): View
    {
        SiteOption::ensureDefaultOptionsExist();

        return view('admin.forum.moderation', [
            'settings' => [
                'enabled' => $this->optionValue('moderation.content-filter.enabled', '1') === '1',
                'custom_patterns' => $this->optionValue('moderation.content-filter.custom-patterns'),
                'block_all_caps' => $this->optionValue('moderation.content-filter.block-all-caps', '1') === '1',
                'min_all_caps_letters' => $this->optionValue('moderation.content-filter.min-all-caps-letters', '12'),
                'max_repeated_character_run' => $this->optionValue('moderation.content-filter.max-repeated-character-run', '6'),
                'max_repeated_word_run' => $this->optionValue('moderation.content-filter.max-repeated-word-run', '4'),
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
        $this->storeOption('moderation.content-filter.block-all-caps', (string) $validated['block_all_caps']);
        $this->storeOption('moderation.content-filter.min-all-caps-letters', (string) $validated['min_all_caps_letters']);
        $this->storeOption('moderation.content-filter.max-repeated-character-run', (string) $validated['max_repeated_character_run']);
        $this->storeOption('moderation.content-filter.max-repeated-word-run', (string) $validated['max_repeated_word_run']);

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
        $result = $this->contentFilter->inspectWithSettings(
            (string) $validated['test_content'],
            'forum',
            $settings,
        );

        return response()->json([
            'blocked' => $result->blocked,
            'rule' => $result->rule,
            'rule_label' => $this->ruleLabel($result->rule),
            'message' => $result->message,
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
            'block_all_caps' => ['required', 'in:0,1'],
            'min_all_caps_letters' => ['required', 'integer', 'min:1', 'max:100'],
            'max_repeated_character_run' => ['required', 'integer', 'min:2', 'max:100'],
            'max_repeated_word_run' => ['required', 'integer', 'min:2', 'max:100'],
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
            'moderation.content-filter.block-all-caps' => (string) $validated['block_all_caps'],
            'moderation.content-filter.min-all-caps-letters' => (string) $validated['min_all_caps_letters'],
            'moderation.content-filter.max-repeated-character-run' => (string) $validated['max_repeated_character_run'],
            'moderation.content-filter.max-repeated-word-run' => (string) $validated['max_repeated_word_run'],
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
