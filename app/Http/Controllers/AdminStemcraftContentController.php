<?php

namespace App\Http\Controllers;

use App\Models\SiteOption;
use App\Support\StemcraftFaqs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStemcraftContentController extends Controller
{
    public function edit(): View
    {
        SiteOption::ensureDefaultOptionsExist();

        return view('admin.stemcraft-content.edit', [
            'monthlyChallenge' => [
                'title' => SiteOption::value('stemcraft.monthly-challenge.title'),
                'description' => SiteOption::value('stemcraft.monthly-challenge.description'),
                'prompt' => SiteOption::value('stemcraft.monthly-challenge.prompt'),
                'image' => SiteOption::value('stemcraft.monthly-challenge.image'),
                'image_alt' => SiteOption::value('stemcraft.monthly-challenge.image-alt'),
            ],
            'communityBuilds' => collect([1, 2, 3])
                ->mapWithKeys(fn (int $index): array => [
                    $index => [
                        'title' => SiteOption::value("stemcraft.community-builds.{$index}.title"),
                        'description' => SiteOption::value("stemcraft.community-builds.{$index}.description"),
                        'image' => SiteOption::value("stemcraft.community-builds.{$index}.image"),
                        'image_alt' => SiteOption::value("stemcraft.community-builds.{$index}.image-alt"),
                    ],
                ])
                ->all(),
            'faqs' => StemcraftFaqs::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_challenge.title' => ['required', 'string', 'max:160'],
            'monthly_challenge.description' => ['required', 'string', 'max:2000'],
            'monthly_challenge.prompt' => ['required', 'string', 'max:1200'],
            'monthly_challenge.image' => ['nullable', 'string', 'max:2048'],
            'monthly_challenge.image_alt' => ['nullable', 'string', 'max:255'],
            'builds' => ['required', 'array'],
            'builds.1.title' => ['required', 'string', 'max:160'],
            'builds.1.description' => ['required', 'string', 'max:700'],
            'builds.1.image' => ['nullable', 'string', 'max:2048'],
            'builds.1.image_alt' => ['nullable', 'string', 'max:255'],
            'builds.2.title' => ['required', 'string', 'max:160'],
            'builds.2.description' => ['required', 'string', 'max:700'],
            'builds.2.image' => ['nullable', 'string', 'max:2048'],
            'builds.2.image_alt' => ['nullable', 'string', 'max:255'],
            'builds.3.title' => ['required', 'string', 'max:160'],
            'builds.3.description' => ['required', 'string', 'max:700'],
            'builds.3.image' => ['nullable', 'string', 'max:2048'],
            'builds.3.image_alt' => ['nullable', 'string', 'max:255'],
            'faqs' => ['required', 'array', 'min:1'],
            'faqs.*.question' => ['required', 'string', 'max:220'],
            'faqs.*.answer' => ['required', 'string', 'max:1200'],
            'faqs.*.show_on_index' => ['nullable', 'boolean'],
        ]);

        $monthlyChallenge = $validated['monthly_challenge'];
        $this->storeOption('stemcraft.monthly-challenge.title', $monthlyChallenge['title']);
        $this->storeOption('stemcraft.monthly-challenge.description', $monthlyChallenge['description']);
        $this->storeOption('stemcraft.monthly-challenge.prompt', $monthlyChallenge['prompt']);
        $this->storeOption('stemcraft.monthly-challenge.image', $monthlyChallenge['image'] ?? '');
        $this->storeOption('stemcraft.monthly-challenge.image-alt', $monthlyChallenge['image_alt'] ?? '');

        foreach ([1, 2, 3] as $index) {
            $build = $validated['builds'][$index];
            $this->storeOption("stemcraft.community-builds.{$index}.title", $build['title']);
            $this->storeOption("stemcraft.community-builds.{$index}.description", $build['description']);
            $this->storeOption("stemcraft.community-builds.{$index}.image", $build['image'] ?? '');
            $this->storeOption("stemcraft.community-builds.{$index}.image-alt", $build['image_alt'] ?? '');
        }

        $faqs = collect($validated['faqs'])
            ->map(fn (array $faq): array => [
                'question' => trim((string) $faq['question']),
                'answer' => trim((string) $faq['answer']),
                'show_on_index' => filter_var($faq['show_on_index'] ?? false, FILTER_VALIDATE_BOOL),
            ])
            ->values()
            ->all();

        $this->storeOption(
            StemcraftFaqs::OPTION,
            json_encode($faqs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]'
        );

        session()->flash('message', 'STEMCraft content updated.');
        session()->flash('message-title', 'Content saved');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.stemcraft-content.edit');
    }

    private function storeOption(string $name, ?string $value): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => $name],
            ['value' => trim((string) $value)]
        );
    }
}
