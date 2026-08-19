<?php

namespace App\Http\Controllers;

use App\Models\NewsletterStoreTheme;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsletterStoreThemeController extends Controller
{
    public function index(): View
    {
        return view('admin.subscription.theme.index', ['themes' => NewsletterStoreTheme::query()->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return $this->editView(new NewsletterStoreTheme(['is_active' => true, 'match_type' => 'random']));
    }

    public function store(Request $request): RedirectResponse
    {
        $theme = NewsletterStoreTheme::query()->create($this->validated($request));
        $this->flash('Subscription store theme created.');

        return redirect()->route('admin.subscription.theme.edit', $theme);
    }

    public function edit(NewsletterStoreTheme $theme): View
    {
        return $this->editView($theme);
    }

    public function update(Request $request, NewsletterStoreTheme $theme): RedirectResponse
    {
        $theme->update($this->validated($request));
        $this->flash('Subscription store theme updated.');

        return redirect()->back();
    }

    public function destroy(NewsletterStoreTheme $theme): RedirectResponse
    {
        $theme->delete();
        $this->flash('Subscription store theme deleted.');

        return redirect()->route('admin.subscription.theme.index');
    }

    private function editView(NewsletterStoreTheme $theme): View
    {
        return view('admin.subscription.theme.edit', [
            'theme' => $theme,
            'categories' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'matchTypes' => NewsletterStoreTheme::MATCH_TYPES,
        ]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:400'],
            'category_slugs' => ['required', 'array', 'min:1'],
            'category_slugs.*' => ['required', 'string', 'exists:product_categories,slug'],
            'match_type' => ['required', 'string', 'in:'.implode(',', array_keys(NewsletterStoreTheme::MATCH_TYPES))],
            'match_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        if (! in_array($validated['match_type'], ['created_within', 'updated_within', 'restocked_within'], true)) {
            $validated['match_days'] = null;
        }

        return $validated;
    }

    private function flash(string $message): void
    {
        session()->flash('message', $message);
        session()->flash('message-title', 'Store theme updated');
        session()->flash('message-type', 'success');
    }
}
