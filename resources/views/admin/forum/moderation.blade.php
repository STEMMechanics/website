<x-layout>
    <x-mast backRoute="admin.forum.category.index" backTitle="Discussion Categories">Discussion Moderation</x-mast>

    <x-container inner-class="max-w-5xl" class="py-8">
        <form method="POST" action="{{ route('admin.forum.moderation.update') }}" class="space-y-6" id="forum-moderation-form">
            @csrf

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">Powered by Blasp</div>
                    <h2 class="mt-4 text-2xl font-semibold text-gray-900">Filter discussion content and STEMCraft messages before they go live</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">Use these settings for the shared moderation stack, with extra checks for things like shouting, repeated spam, Minecraft message masking, and admin alert timing.</p>
                </div>

                <div class="mt-6 rounded-2xl bg-gray-50 p-4">
                    <input type="hidden" name="enabled" value="0" />
                    <x-ui.checkbox
                        label="Enable content filtering"
                        name="enabled"
                        value="1"
                        :checked="$settings['enabled']"
                    />
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Custom patterns</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Add extra match rules here when you want to catch specific shorthand, phrases, or edge cases.</p>

                <div class="mt-6 rounded-2xl bg-gray-50 p-4">
                    <x-ui.input
                        type="textarea"
                        label="Custom regex patterns"
                        name="custom_patterns"
                        rows="8"
                        value="{{ $settings['custom_patterns'] }}"
                        info="One regex per line, without delimiters. Use boundaries for exact matches, for example `\\bfck\\b` will match `fck` but not `bofck`."
                    />
                </div>

                <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-4" id="regex-tester">
                    <h3 class="text-sm font-semibold text-gray-900">Regex tester</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Try a single regex pattern against sample text before adding it to the list above.</p>

                    <div class="mt-4 grid gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                        <x-ui.input
                            label="Pattern to test"
                            name="regex_test_pattern"
                            value=""
                            info="Enter one pattern without delimiters. Example: `\\bfck\\b`."
                        />
                        <x-ui.input
                            type="textarea"
                            label="Sample text"
                            name="regex_test_content"
                            rows="4"
                            value=""
                            info="Try a word, sentence, or a few examples at once."
                        />
                    </div>

                    <div class="mt-4 rounded-2xl bg-gray-50 px-4 py-3 text-sm text-gray-700" id="regex-tester-result">
                        Enter a pattern and some sample text to see whether it matches.
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Exception words</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Use this list for false positives that Blasp should ignore, such as harmless words or phrases that look suspicious out of context.</p>

                <div class="mt-6 rounded-2xl bg-gray-50 p-4">
                    <x-ui.input
                        type="textarea"
                        label="Exception words"
                        name="exception_words"
                        rows="8"
                        value="{{ $settings['exception_words'] }}"
                        info="One exact word or phrase per line. Matching ignores spaces and punctuation, so `snot` also covers `s not` and `s.not`. HTML-escaped apostrophes are normalized automatically."
                    />
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Blasp severity</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Set a minimum severity if you want to ignore milder profanity and only block stronger matches.</p>

                <div class="mt-6 rounded-2xl bg-gray-50 p-4">
                    <x-ui.select
                        label="Minimum profanity severity"
                        name="minimum_severity"
                        info="Mild keeps the current behaviour. Raise this to ignore words below the chosen threshold."
                    >
                        <option value="mild" {{ $settings['minimum_severity'] === 'mild' ? 'selected' : '' }}>Mild</option>
                        <option value="moderate" {{ $settings['minimum_severity'] === 'moderate' ? 'selected' : '' }}>Moderate</option>
                        <option value="high" {{ $settings['minimum_severity'] === 'high' ? 'selected' : '' }}>High</option>
                        <option value="extreme" {{ $settings['minimum_severity'] === 'extreme' ? 'selected' : '' }}>Extreme</option>
                    </x-ui.select>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Pattern rules</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Use these rules to catch things like shouting, repeated characters, or repeated words before a post goes live.</p>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <input type="hidden" name="block_all_caps" value="0" />
                        <x-ui.checkbox
                            label="Block all-caps messages"
                            name="block_all_caps"
                            value="1"
                            :checked="$settings['block_all_caps']"
                        />
                        <x-ui.input
                            type="number"
                            label="Minimum letters before all-caps is blocked"
                            name="min_all_caps_letters"
                            value="{{ $settings['min_all_caps_letters'] }}"
                        />
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-4">
                        <x-ui.input
                            type="number"
                            label="Maximum repeated character run"
                            name="max_repeated_character_run"
                            value="{{ $settings['max_repeated_character_run'] }}"
                            info="Example: a run of `7` blocks `nooooooo` if the limit is `6`."
                        />
                        <x-ui.input
                            type="number"
                            label="Maximum repeated word run"
                            name="max_repeated_word_run"
                            value="{{ $settings['max_repeated_word_run'] }}"
                            info="Example: `spam spam spam spam` can be blocked once it exceeds the configured run."
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-semibold text-gray-900">Minecraft messaging</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">These settings control how blocked Minecraft messages are masked, displayed, and grouped for admin alerts.</p>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <x-ui.input
                            label="Profanity mask character"
                            name="profanity_mask_character"
                            value="{{ $settings['profanity_mask_character'] }}"
                            info="Used when profanity can be masked for Minecraft chat, books, signs, and similar messages."
                        />
                        <x-ui.input
                            type="textarea"
                            label="Blocked message placeholder"
                            name="blocked_message_placeholder"
                            rows="3"
                            value="{{ $settings['blocked_message_placeholder'] }}"
                            info="Shown when a blocked Minecraft message has no filtered text that can be displayed safely."
                        />
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-4">
                        <x-ui.input
                            type="number"
                            label="Blocked message alert quiet period (minutes)"
                            name="message_failure_notification_delay_minutes"
                            value="{{ $settings['message_failure_notification_delay_minutes'] }}"
                            info="Admins will be emailed only after there has been a quiet period of this length since the latest blocked Minecraft message."
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8" id="filter-tester">
                <h2 class="text-xl font-semibold text-gray-900">Full filter tester</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Test text against the full moderation stack using the current form values, even before you save them.</p>

                <div class="mt-6">
                    <x-ui.input
                        type="textarea"
                        label="Text to test"
                        name="filter_test_content"
                        rows="5"
                        value=""
                        info="Paste the exact sentence, phrase, or post content you want to test."
                    />
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <x-ui.select
                        label="Preview severity"
                        name="preview_minimum_severity"
                        info="Override the saved Blasp severity for this test run only."
                    >
                        <option value="">Current setting</option>
                        <option value="mild" {{ $settings['minimum_severity'] === 'mild' ? 'selected' : '' }}>Mild</option>
                        <option value="moderate" {{ $settings['minimum_severity'] === 'moderate' ? 'selected' : '' }}>Moderate</option>
                        <option value="high" {{ $settings['minimum_severity'] === 'high' ? 'selected' : '' }}>High</option>
                        <option value="extreme" {{ $settings['minimum_severity'] === 'extreme' ? 'selected' : '' }}>Extreme</option>
                    </x-ui.select>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <x-ui.button type="button" id="run-filter-test">Run moderation test</x-ui.button>
                        <div class="text-sm text-gray-500" id="filter-tester-status">No test run yet.</div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl bg-gray-50 px-4 py-4 text-sm text-gray-700" id="filter-tester-result">
                    Run a test to see whether the current settings would block the text, which rule would do it, and whether a Minecraft-safe masked version is available.
                </div>
            </section>

            <div class="flex justify-end">
                <x-ui.button type="submit">Save moderation settings</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('forum-moderation-form');
        if (!form) {
            return;
        }

        const regexPatternInput = form.querySelector('[name="regex_test_pattern"]');
        const regexContentInput = form.querySelector('[name="regex_test_content"]');
        const regexResult = document.getElementById('regex-tester-result');
        const filterContentInput = form.querySelector('[name="filter_test_content"]');
        const previewSeverityInput = form.querySelector('[name="preview_minimum_severity"]');
        const filterRunButton = document.getElementById('run-filter-test');
        const filterStatus = document.getElementById('filter-tester-status');
        const filterResult = document.getElementById('filter-tester-result');

        const setRegexResult = (message, tone = 'neutral') => {
            if (!regexResult) {
                return;
            }

            regexResult.className = `mt-4 rounded-2xl px-4 py-3 text-sm ${tone === 'success'
                ? 'bg-green-50 text-green-800'
                : tone === 'danger'
                    ? 'bg-red-50 text-red-800'
                    : 'bg-gray-50 text-gray-700'}`;
            regexResult.textContent = message;
        };

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const formatSeverity = (value) => String(value || '').trim().toUpperCase();

        const renderKvRow = (label, value, mono = false) => {
            if (value === null || value === undefined) {
                return '';
            }

            const text = String(value).trim();
            if (text === '') {
                return '';
            }

            return `
                <div class="grid grid-cols-[11rem_minmax(0,1fr)] items-start gap-x-6 py-3">
                    <div class="text-sm font-semibold text-amber-800">${escapeHtml(label)}</div>
                    <div class="${mono ? 'font-mono text-sm leading-6 text-amber-950 break-words' : 'text-sm leading-6 text-amber-950 break-words'}">${escapeHtml(text)}</div>
                </div>
            `;
        };

        const renderBlockedResult = (data, sample) => {
            const rows = [];

            rows.push(renderKvRow('Rule', data.rule_label || data.rule || 'Blocked'));
            rows.push(renderKvRow('Message', data.message || 'This text would be blocked.'));
            rows.push(renderKvRow('Threshold', data.applied_minimum_severity ? formatSeverity(data.applied_minimum_severity) : null));
            rows.push(renderKvRow('Detected severity', data.profanity_severity ? formatSeverity(data.profanity_severity) : null));
            rows.push(renderKvRow('Score', typeof data.profanity_score === 'number' ? String(data.profanity_score) : null));
            rows.push(renderKvRow('Detail', data.detail || null));

            const matchedTerms = Array.isArray(data.profanity_words)
                ? data.profanity_words.map((word) => {
                    const text = String(word?.text || '').trim();
                    const base = String(word?.base || '').trim();
                    const severity = String(word?.severity || '').trim();

                    return {
                        captured: text || base,
                        resolved: base || text,
                        severity: severity !== '' ? formatSeverity(severity) : '',
                    };
                }).filter((word) => word.captured !== '' || word.resolved !== '')
                : [];

            if (matchedTerms.length > 0) {
                rows.push(`
                    <div class="py-3">
                        <div class="text-sm font-semibold text-amber-800">Captured / resolved</div>
                        <div class="mt-3 space-y-4">
                            ${matchedTerms.map((word, index) => `
                                <div class="rounded-xl border border-amber-200 px-4 ${index % 2 === 0 ? 'bg-amber-100/60' : 'bg-white/70'}">
                                    ${renderKvRow('Captured text', word.captured, true)}
                                    ${renderKvRow('Resolves as', word.resolved, true)}
                                    ${renderKvRow('Severity', word.severity || 'n/a', true)}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `);
            }

            const filteredOutput = typeof data.filtered_message === 'string' && data.filtered_message.trim() !== '' && data.filtered_message !== sample
                ? data.filtered_message
                : '';
            const placeholder = typeof data.blocked_message_placeholder === 'string' ? data.blocked_message_placeholder.trim() : '';

            if (filteredOutput !== '') {
                rows.push(renderKvRow('Filtered output', filteredOutput, true));
            } else {
                rows.push(renderKvRow('Placeholder', placeholder, true));
            }

            return `
                <div class="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50/80 px-4">
                    <div>
                        ${rows.filter(Boolean).join('')}
                    </div>
                </div>
            `;
        };

        const updateRegexTest = () => {
            const pattern = String(regexPatternInput?.value || '').trim();
            const sample = String(regexContentInput?.value || '');

            if (pattern === '' && sample.trim() === '') {
                setRegexResult('Enter a pattern and some sample text to see whether it matches.');
                return;
            }

            if (pattern === '') {
                setRegexResult('Add a pattern first.', 'danger');
                return;
            }

            try {
                const regex = new RegExp(pattern, 'iu');
                const matches = [...sample.matchAll(new RegExp(pattern, 'igu'))].map((match) => match[0]).filter(Boolean);

                if (matches.length > 0) {
                    const preview = matches.slice(0, 3).join(', ');
                    setRegexResult(`Matched ${matches.length} time${matches.length === 1 ? '' : 's'}: ${preview}`, 'success');
                    return;
                }

                setRegexResult('No match found in the sample text.');
            } catch (error) {
                setRegexResult(error instanceof Error ? error.message : 'Invalid regular expression.', 'danger');
            }
        };

        regexPatternInput?.addEventListener('input', updateRegexTest);
        regexContentInput?.addEventListener('input', updateRegexTest);

        filterRunButton?.addEventListener('click', async () => {
            if (!filterContentInput || !filterStatus || !filterResult) {
                return;
            }

            const sample = String(filterContentInput.value || '').trim();
            if (sample === '') {
                filterStatus.textContent = 'Add some text to test first.';
                filterResult.className = 'mt-4 rounded-2xl bg-red-50 px-4 py-4 text-sm text-red-800';
                filterResult.textContent = 'The full filter tester needs some text to check.';
                return;
            }

            filterRunButton.disabled = true;
            const previewSeverity = String(previewSeverityInput?.value || '').trim();
            filterStatus.textContent = previewSeverity !== ''
                ? `Testing current settings at ${previewSeverity} preview severity...`
                : 'Testing current settings...';

            const payload = new FormData(form);
            payload.set('test_content', sample);
            payload.set('preview_minimum_severity', previewSeverity);

            try {
                const response = await fetch(@js(route('admin.forum.moderation.preview')), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                    },
                    credentials: 'same-origin',
                    body: payload,
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMessage = data?.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : 'The current settings could not be tested.';
                    filterStatus.textContent = 'Test failed.';
                    filterResult.className = 'mt-4 rounded-2xl bg-red-50 px-4 py-4 text-sm text-red-800';
                    filterResult.textContent = errorMessage;
                    return;
                }

                if (data.blocked) {
                    filterStatus.textContent = 'Blocked';
                    filterResult.className = 'mt-4 rounded-2xl bg-amber-50 px-4 py-4 text-sm text-amber-900';
                    filterResult.innerHTML = renderBlockedResult(data, sample);
                    return;
                }

                filterStatus.textContent = 'Allowed';
                filterResult.className = 'mt-4 rounded-2xl bg-green-50 px-4 py-4 text-sm text-green-800';
                filterResult.textContent = 'This text would be allowed by the current settings.';
            } catch (_error) {
                filterStatus.textContent = 'Test failed.';
                filterResult.className = 'mt-4 rounded-2xl bg-red-50 px-4 py-4 text-sm text-red-800';
                filterResult.textContent = 'The full filter tester could not reach the server.';
            } finally {
                filterRunButton.disabled = false;
            }
        });
    });
</script>
