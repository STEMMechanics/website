<?php

namespace App\Services;

use App\Models\NewsletterProductPromotion;
use App\Models\NewsletterStoreTheme;
use App\Models\Product;
use Illuminate\Support\Collection;

class NewsletterProductSelectionService
{
    public const PRODUCTS_PER_SECTION = 3;

    /** @return array<string, mixed> */
    public function selection(): array
    {
        $promotion = $this->draft();

        return [
            'source' => 'draft',
            'draft_id' => $promotion->id,
            'sections' => $this->hydrateSections($promotion->sections ?? []),
            'subject' => $promotion->subject,
            'hero_header' => $promotion->hero_header,
            'hero_cta' => $promotion->hero_cta,
            'content_order' => $promotion->content_order,
        ];
    }

    /** @param array{subject:string,hero_header:string,hero_cta:string,content_order:string} $presentation */
    public function lockPresentation(NewsletterProductPromotion $promotion, array $presentation): NewsletterProductPromotion
    {
        if (filled($promotion->subject) && filled($promotion->hero_header) && filled($promotion->hero_cta)
            && in_array($promotion->content_order, ['store', 'workshops'], true)) {
            return $promotion;
        }

        $promotion->update($presentation);

        return $promotion->fresh();
    }

    /** @param array<string, string> $presentation */
    public function savePresentation(NewsletterProductPromotion $promotion, array $presentation): NewsletterProductPromotion
    {
        $promotion->update($presentation);

        return $promotion->fresh();
    }

    public function clearPresentation(NewsletterProductPromotion $promotion): NewsletterProductPromotion
    {
        $promotion->update([
            'subject' => null,
            'hero_header' => null,
            'hero_cta' => null,
            'content_order' => null,
        ]);

        return $promotion->fresh();
    }

    public function draft(): NewsletterProductPromotion
    {
        $promotion = NewsletterProductPromotion::query()->latest('updated_at')->first();
        if (! $promotion instanceof NewsletterProductPromotion) {
            $promotion = NewsletterProductPromotion::query()->create([
                'sections' => $this->defaultSections(),
                'is_active' => true,
            ]);
        }

        $sections = $this->normalizeSections($promotion->sections ?? []);
        if ($sections !== ($promotion->sections ?? [])) {
            $promotion->update(['sections' => $sections]);
        }

        foreach ($sections as $index => $section) {
            if ($section['theme'] !== 'disabled' && $section['product_ids'] === []) {
                $promotion = $this->refreshSection($promotion, $index);
            }
        }

        return $promotion->fresh();
    }

    /** @param array<int, array<string, mixed>> $sections */
    public function saveSections(NewsletterProductPromotion $promotion, array $sections): NewsletterProductPromotion
    {
        $existing = collect($this->normalizeSections($promotion->sections ?? []))->keyBy('key');
        $normalized = collect($this->normalizeSections($sections))->map(function (array $section) use ($existing): array {
            $previous = $existing->get($section['key']);
            if (is_array($previous) && $previous['locked_product_ids'] !== [] && $section['category_slugs'] !== $previous['category_slugs']) {
                $section['category_slugs'] = $previous['category_slugs'];
            }

            return $section;
        })->all();

        $promotion->update(['sections' => $normalized, 'is_active' => true]);

        return $promotion->fresh();
    }

    public function refreshSection(NewsletterProductPromotion $promotion, int $sectionIndex): NewsletterProductPromotion
    {
        $sections = $this->normalizeSections($promotion->sections ?? []);
        if (! isset($sections[$sectionIndex])) {
            return $promotion;
        }

        $section = $sections[$sectionIndex];
        if ($section['theme'] === 'disabled') {
            return $promotion;
        }
        $lockedIds = collect($section['locked_product_ids'])->intersect($section['product_ids'])->values();
        $replaceableIds = collect($section['product_ids'])->diff($lockedIds);
        $excludeIds = collect($sections)->flatMap(fn (array $item) => $item['product_ids'])->merge($replaceableIds)->unique();
        $needed = self::PRODUCTS_PER_SECTION - $lockedIds->count();

        $fresh = $this->candidates($section['category_slugs'], $this->resolveTheme($section))
            ->whereNotIn('id', $excludeIds)
            ->take($needed);
        if ($fresh->count() < $needed) {
            $fresh = $fresh->concat(
                $this->candidates($section['category_slugs'], $this->resolveTheme($section))
                    ->whereNotIn('id', $lockedIds)
                    ->whereNotIn('id', $fresh->pluck('id'))
                    ->take($needed - $fresh->count())
            );
        }

        $sections[$sectionIndex]['product_ids'] = $lockedIds->concat($fresh->pluck('id'))->take(self::PRODUCTS_PER_SECTION)->values()->all();
        $promotion->update(['sections' => $sections]);

        return $promotion->fresh();
    }

    public function refreshCopy(NewsletterProductPromotion $promotion, int $sectionIndex): NewsletterProductPromotion
    {
        $sections = $this->normalizeSections($promotion->sections ?? []);
        if (! isset($sections[$sectionIndex])) {
            return $promotion;
        }

        $suggestions = $this->copySuggestions($sectionIndex);
        $nextIndex = (((int) $sections[$sectionIndex]['suggestion_index']) + 1) % count($suggestions);
        $sections[$sectionIndex]['title'] = $suggestions[$nextIndex]['title'];
        $sections[$sectionIndex]['intro'] = $suggestions[$nextIndex]['intro'];
        $sections[$sectionIndex]['suggestion_index'] = $nextIndex;
        $promotion->update(['sections' => $sections]);

        return $promotion->fresh();
    }

    public function applyTheme(NewsletterProductPromotion $promotion, int $sectionIndex, int $themeId): NewsletterProductPromotion
    {
        $sections = $this->normalizeSections($promotion->sections ?? []);
        $theme = NewsletterStoreTheme::query()->whereKey($themeId)->where('is_active', true)->first();
        if (! isset($sections[$sectionIndex]) || ! $theme instanceof NewsletterStoreTheme) {
            return $promotion;
        }

        $sections[$sectionIndex]['theme_id'] = $theme->id;
        $sections[$sectionIndex]['theme'] = 'managed';
        $sections[$sectionIndex]['title'] = $theme->title;
        $sections[$sectionIndex]['intro'] = (string) $theme->intro;
        if ($sections[$sectionIndex]['locked_product_ids'] === []) {
            $sections[$sectionIndex]['category_slugs'] = collect($theme->category_slugs)->filter()->values()->all();
        }
        $sections[$sectionIndex]['product_ids'] = collect($sections[$sectionIndex]['product_ids'])->intersect($sections[$sectionIndex]['locked_product_ids'])->values()->all();
        $promotion->update(['sections' => $sections]);

        return $this->refreshSection($promotion->fresh(), $sectionIndex);
    }

    public function refreshProduct(NewsletterProductPromotion $promotion, int $sectionIndex, int $slot): NewsletterProductPromotion
    {
        $sections = $this->normalizeSections($promotion->sections ?? []);
        $section = $sections[$sectionIndex] ?? null;
        $currentId = $section['product_ids'][$slot] ?? null;
        if (! is_array($section) || $currentId === null || in_array($currentId, $section['locked_product_ids'], true)) {
            return $promotion;
        }

        $excludedIds = collect($sections)->flatMap(fn (array $item) => $item['product_ids'])->unique();
        $replacement = $this->candidates($section['category_slugs'], $this->resolveTheme($section))->first(fn (Product $product): bool => ! $excludedIds->contains($product->id));
        if ($replacement instanceof Product) {
            $sections[$sectionIndex]['product_ids'][$slot] = $replacement->id;
            $promotion->update(['sections' => $sections]);
        }

        return $promotion->fresh();
    }

    public function themeHasCandidates(NewsletterStoreTheme $theme): bool
    {
        return $this->candidates(
            collect($theme->category_slugs)->map(fn ($slug): string => (string) $slug)->filter()->values()->all(),
            $theme,
        )->isNotEmpty();
    }

    public function clearLocks(NewsletterProductPromotion $promotion): void
    {
        $sections = collect($this->normalizeSections($promotion->sections ?? []))
            ->map(function (array $section): array {
                $section['locked_product_ids'] = [];

                return $section;
            })->all();

        $promotion->update(['sections' => $sections]);
    }

    /** @param array<int, array<string, mixed>> $sections @return Collection<int, array<string, mixed>> */
    private function hydrateSections(array $sections): Collection
    {
        return collect($this->normalizeSections($sections))
            ->reject(fn (array $section): bool => $section['theme'] === 'disabled')
            ->map(function (array $section): array {
                $products = Product::query()
                    ->with(['hero', 'categories', 'variants'])
                    ->active()
                    ->whereIn('id', $section['product_ids'])
                    ->get()
                    ->keyBy('id');

                $section['products'] = collect($section['product_ids'])
                    ->map(fn (int $id) => $products->get($id))
                    ->filter(fn ($product) => $product instanceof Product)
                    ->filter(fn (Product $product): bool => $this->isAvailable($product))
                    ->values();

                return $section;
            })->filter(fn (array $section): bool => $section['products']->isNotEmpty())->values();
    }

    /** @return Collection<int, Product> */
    private function candidates(array $categorySlugs, ?NewsletterStoreTheme $theme = null): Collection
    {
        $query = Product::query()
            ->with(['hero', 'categories', 'variants'])
            ->active()
            ->when($categorySlugs !== [], fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->whereIn('slug', $categorySlugs)));

        if ($theme === null) {
            return $query->latest('created_at')->limit(60)->get()->filter(fn (Product $product): bool => $this->isAvailable($product))->values();
        }

        $days = max(1, (int) ($theme->match_days ?? 7));
        match ($theme->match_type) {
            'created_within' => $query->where('created_at', '>=', now()->subDays($days))->latest('created_at'),
            'updated_within' => $query->where('updated_at', '>=', now()->subDays($days))->latest('updated_at'),
            'restocked_within' => $query->whereNotNull('restocked_at')->where('restocked_at', '>=', now()->subDays($days))->orderByDesc('restocked_at'),
            'featured' => $query->where('is_featured', true)->latest('created_at'),
            'random' => $query->inRandomOrder(),
            default => $query->latest('created_at'),
        };

        return $query
            ->limit(60)
            ->get()
            ->filter(fn (Product $product): bool => $this->isAvailable($product))
            ->values();
    }

    private function isAvailable(Product $product): bool
    {
        return $product->isSelectionPurchasable()
            || $product->purchasableVariants()->contains(fn ($variant): bool => $product->isSelectionPurchasable($variant));
    }

    /** @param array<int, array<string, mixed>> $sections @return array<int, array<string, mixed>> */
    private function normalizeSections(array $sections): array
    {
        $defaults = $this->defaultSections();

        return collect([0, 1])->map(function (int $index) use ($sections, $defaults): array {
            $section = is_array($sections[$index] ?? null) ? $sections[$index] : [];
            $productIds = collect($section['product_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->take(3)->values();
            $legacyCategorySlugs = $index === 1 && ($section['category_slug'] ?? null) === 'materials'
                ? $defaults[$index]['category_slugs']
                : [$section['category_slug'] ?? null];
            $categorySlugs = collect($section['category_slugs'] ?? $legacyCategorySlugs)
                ->map(fn ($slug): string => trim((string) $slug))
                ->filter()
                ->unique()
                ->values();

            return [
                'key' => (string) ($defaults[$index]['key']),
                'title' => trim((string) ($section['title'] ?? $defaults[$index]['title'])),
                'intro' => trim((string) ($section['intro'] ?? '')),
                'category_slugs' => $categorySlugs->isNotEmpty() ? $categorySlugs->all() : $defaults[$index]['category_slugs'],
                'product_ids' => $productIds->all(),
                'locked_product_ids' => collect($section['locked_product_ids'] ?? [])->map(fn ($id): int => (int) $id)->intersect($productIds)->values()->all(),
                'suggestion_index' => max(0, (int) ($section['suggestion_index'] ?? 0)),
                'theme' => trim((string) ($section['theme'] ?? 'new')),
                'theme_id' => in_array(($section['theme'] ?? 'managed'), ['custom', 'disabled'], true)
                    ? null
                    : (isset($section['theme_id']) ? (int) $section['theme_id'] : $this->defaultThemeId($index)),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function defaultSections(): array
    {
        return [
            ['key' => 'kits', 'title' => 'New kits arrived', 'intro' => 'Fresh project ideas for curious makers.', 'category_slugs' => ['kits'], 'product_ids' => [], 'locked_product_ids' => [], 'suggestion_index' => 0, 'theme' => 'managed', 'theme_id' => $this->defaultThemeId(0)],
            ['key' => 'extras', 'title' => 'Grab some extras', 'intro' => 'Stock up on useful materials and parts for the next build.', 'category_slugs' => ['materials', 'parts'], 'product_ids' => [], 'locked_product_ids' => [], 'suggestion_index' => 0, 'theme' => 'managed', 'theme_id' => $this->defaultThemeId(1)],
        ];
    }

    /** @return array<int, array{title:string,intro:string}> */
    private function copySuggestions(int $sectionIndex): array
    {
        if ($sectionIndex === 0) {
            return [
                ['title' => 'New kits arrived', 'intro' => 'Fresh project ideas for curious makers.'],
                ['title' => 'Recently updated kits', 'intro' => 'Take another look at these refreshed hands-on projects.'],
                ['title' => 'Kits back in stock', 'intro' => 'Popular projects are ready for your next making session.'],
                ['title' => 'Kits worth discovering', 'intro' => 'A few engaging ways to build, test and create.'],
            ];
        }

        return [
            ['title' => 'Grab some extras', 'intro' => 'Stock up on useful materials and parts for the next build.'],
            ['title' => 'New materials and parts', 'intro' => 'Fresh supplies for prototypes, repairs and creative experiments.'],
            ['title' => 'Back in stock', 'intro' => 'Useful workshop favourites are available again.'],
            ['title' => 'Complete your next build', 'intro' => 'Handy components and materials to keep ideas moving.'],
        ];
    }

    /** @return array<int, array{key:string,label:string,title:string,intro:string}> */
    public function themeSuggestions(int $sectionIndex): array
    {
        $titles = $this->copySuggestions($sectionIndex);
        $keys = ['new', 'updated', 'restocked', 'featured'];
        $labels = ['New arrivals', 'Recently updated', 'Back in stock', 'Featured picks'];

        return collect($titles)->map(fn (array $copy, int $index): array => [
            'key' => $keys[$index],
            'label' => $labels[$index],
            'title' => $copy['title'],
            'intro' => $copy['intro'],
        ])->all();
    }

    /** @param array<string, mixed> $section */
    private function resolveTheme(array $section): ?NewsletterStoreTheme
    {
        $themeId = (int) ($section['theme_id'] ?? 0);

        return $themeId > 0 ? NewsletterStoreTheme::query()->find($themeId) : null;
    }

    private function defaultThemeId(int $sectionIndex): ?int
    {
        return NewsletterStoreTheme::query()
            ->where('name', $sectionIndex === 0 ? 'New Kit Arrivals' : 'Grab Extras')
            ->value('id');
    }
}
