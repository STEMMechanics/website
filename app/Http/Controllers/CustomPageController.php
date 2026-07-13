<?php

namespace App\Http\Controllers;

use App\Models\CustomPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomPageController extends Controller
{
    public function showByPath(string $path): View
    {
        $page = $this->findPublishedPage($path);
        abort_if($page === null, 404);

        return view('custom-page.show', [
            'page' => $page,
        ]);
    }

    public function fallback(Request $request): View|RedirectResponse
    {
        $page = $this->findPublishedPage($request->path());
        if ($page !== null) {
            return view('custom-page.show', [
                'page' => $page,
            ]);
        }

        $aliasMatch = $this->findPublishedAliasMatch($request->path());
        if ($aliasMatch !== null) {
            return redirect()->to($aliasMatch->path, 301);
        }

        abort(404);
    }

    public function linkOptions(): JsonResponse
    {
        $customPages = CustomPage::query()
            ->published()
            ->orderBy('title')
            ->get(['title', 'path'])
            ->map(fn (CustomPage $page) => [
                'title' => $page->title,
                'path' => $page->path,
            ]);

        return response()->json([
            'pages' => $this->staticLinkOptions()
                ->merge($customPages)
                ->unique(fn (array $page) => (string) $page['path'])
                ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
        ]);
    }

    public function adminIndex(Request $request): View
    {
        $query = CustomPage::query()->with(['hero', 'author'])->orderBy('path');
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('path', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        return view('admin.custom-page.index', [
            'pages' => $query->paginate(20)->onEachSide(1),
        ]);
    }

    public function adminCreate(): View
    {
        return view('admin.custom-page.edit', [
            'page' => new CustomPage([
                'is_published' => true,
                'show_mast' => true,
            ]),
            'editing' => false,
        ]);
    }

    public function adminStore(Request $request): RedirectResponse
    {
        $page = new CustomPage();
        $this->savePage($request, $page);

        session()->flash('message', 'Custom page created.');
        session()->flash('message-title', 'Custom page created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.custom-page.edit', $page);
    }

    public function adminEdit(CustomPage $customPage): View
    {
        return view('admin.custom-page.edit', [
            'page' => $customPage->load(['hero', 'author']),
            'editing' => true,
        ]);
    }

    public function adminUpdate(Request $request, CustomPage $customPage): RedirectResponse
    {
        $this->savePage($request, $customPage);

        session()->flash('message', 'Custom page updated.');
        session()->flash('message-title', 'Custom page updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.custom-page.edit', $customPage);
    }

    public function adminDestroy(CustomPage $customPage): RedirectResponse
    {
        $customPage->delete();

        session()->flash('message', 'Custom page deleted.');
        session()->flash('message-title', 'Custom page deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.custom-page.index');
    }

    private function findPublishedPage(string $path): ?CustomPage
    {
        return CustomPage::query()
            ->published()
            ->where('path', CustomPage::normalizePath($path))
            ->first();
    }

    private function findPublishedAliasMatch(string $path): ?CustomPage
    {
        $normalizedPath = CustomPage::normalizePath($path);

        return CustomPage::query()
            ->published()
            ->whereJsonContains('aliases', $normalizedPath)
            ->first();
    }

    private function savePage(Request $request, CustomPage $page): void
    {
        $normalizedPath = CustomPage::normalizePath((string) $request->input('path', ''));
        $aliases = preg_split('/\R+/', trim((string) $request->input('aliases', ''))) ?: [];
        $normalizedAliases = CustomPage::normalizeAliases($aliases, $normalizedPath, $page->id);

        $request->merge([
            'path' => $normalizedPath,
            'aliases' => $normalizedAliases,
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'path' => [
                'required',
                'string',
                'max:200',
                'regex:/^\/?[A-Za-z0-9\-\/]+$/',
                Rule::unique('custom_pages', 'path')->ignore($page->id),
            ],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => [
                'string',
                'max:200',
                'regex:/^\/?[A-Za-z0-9\-\/]+$/',
                Rule::notIn([$normalizedPath]),
                Rule::unique('custom_pages', 'path'),
            ],
            'content' => ['required', 'string'],
            'hero_media_name' => ['nullable', 'string', 'exists:media,name'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($normalizedAliases as $alias) {
            $aliasInUse = CustomPage::query()
                ->when($page->exists, fn ($query) => $query->where('id', '!=', $page->id))
                ->where(function ($query) use ($alias): void {
                    $query->where('path', $alias)
                        ->orWhereJsonContains('aliases', $alias);
                })
                ->exists();

            if ($aliasInUse) {
                throw ValidationException::withMessages([
                    'aliases' => 'One or more aliases are already in use by another page.',
                ]);
            }
        }

        $page->title = trim((string) $validated['title']);
        $page->path = CustomPage::normalizePath((string) $validated['path']);
        $page->aliases = $normalizedAliases;
        $page->content = (string) $validated['content'];
        $page->hero_media_name = trim((string) ($validated['hero_media_name'] ?? '')) ?: null;
        $page->show_mast = $request->boolean('show_mast');
        $page->seo_title = trim((string) ($validated['seo_title'] ?? '')) ?: null;
        $page->seo_description = trim((string) ($validated['seo_description'] ?? '')) ?: null;
        $page->seo_noindex = $request->boolean('seo_noindex');
        $page->is_published = $request->boolean('is_published', true);
        $page->user_id = (string) $request->user()->id;
        $page->save();
    }

    private function staticLinkOptions(): Collection
    {
        return collect([
            ['title' => 'Home', 'path' => '/'],
            ['title' => 'About', 'path' => '/about'],
            ['title' => 'Contact', 'path' => '/contact'],
            ['title' => 'Code of Conduct', 'path' => '/code-of-conduct'],
            ['title' => 'Privacy', 'path' => '/privacy'],
            ['title' => 'Terms & Conditions', 'path' => '/terms-conditions'],
            ['title' => 'Workshops', 'path' => '/workshops'],
            ['title' => 'STEMCraft', 'path' => '/stemcraft'],
            ['title' => 'Join STEMCraft', 'path' => '/stemcraft/join'],
            ['title' => 'STEMCraft Rules', 'path' => '/stemcraft/rules'],
            ['title' => 'STEMCraft FAQs', 'path' => '/stemcraft/faqs'],
        ]);
    }
}
