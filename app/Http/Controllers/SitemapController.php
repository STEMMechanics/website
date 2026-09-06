<?php

namespace App\Http\Controllers;

use App\Models\CustomPage;
use App\Models\Location;
use App\Models\Product;
use App\Models\Workshop;
use App\Support\ShopAvailability;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $static = collect(['index', 'workshop.index', 'workshop.past.index', 'stemcraft.index', 'stemcraft.join', 'stemcraft.rules', 'stemcraft.faqs', 'about', 'contact', 'privacy', 'terms-conditions', 'code-of-conduct'])
            ->map(fn (string $route) => ['loc' => route($route), 'lastmod' => null]);
        $suburbs = Location::query()->whereNotNull('suburb')->where('suburb', '!=', '')
            ->whereIn('id', Workshop::query()->publiclyVisible()->where('is_private', false)->select('location_id'))
            ->get(['suburb', 'updated_at'])->unique(fn (Location $location) => Str::lower(trim($location->suburb)))
            ->map(fn (Location $location) => ['loc' => route('workshop.suburb', Str::slug($location->suburb)), 'lastmod' => $location->updated_at]);
        $static = $static->concat($suburbs);
        $staticPaths = $static->map(fn (array $page) => parse_url($page['loc'], PHP_URL_PATH) ?: '/')->all();
        $managedStatic = CustomPage::query()->published()->whereIn('path', $staticPaths)->get()->keyBy('path');
        $static = $static->filter(fn (array $page) => ! ($managedStatic->get(parse_url($page['loc'], PHP_URL_PATH) ?: '/')->seo_noindex ?? false))
            ->map(function (array $page) use ($managedStatic) {
                $managed = $managedStatic->get(parse_url($page['loc'], PHP_URL_PATH) ?: '/');
                $page['lastmod'] = $managed->updated_at ?? $page['lastmod'];
                return $page;
            })->values();
        $custom = CustomPage::query()->whereNotIn('path', $staticPaths)->published()->where('seo_noindex', false)->where('path', 'like', '/%')->where('path', 'not like', '//%');
        $products = Product::query()->active();
        if (! app(ShopAvailability::class)->isPublicEnabled()) {
            $products->whereRaw('1 = 0');
        } else {
            $static->push(['loc' => route('shop.index'), 'lastmod' => null]);
        }
        $workshops = Workshop::query()->publiclyVisible();
        $segments = [
            [$custom, fn (CustomPage $page) => ['loc' => rtrim(config('app.url'), '/').$page->path, 'lastmod' => $page->updated_at]],
            [$products, fn (Product $product) => ['loc' => route('shop.product.show', $product), 'lastmod' => $product->updated_at]],
            [$workshops, fn (Workshop $workshop) => ['loc' => route('workshop.show', $workshop), 'lastmod' => $workshop->updated_at]],
        ];
        $counts = array_map(fn (array $segment) => (clone $segment[0])->count(), $segments);
        $perPage = 1000;
        $pageCount = max(1, (int) ceil(($static->count() + array_sum($counts)) / $perPage));
        $page = request()->integer('page', 0);
        abort_if($page < 0 || $page > $pageCount, 404);
        if ($pageCount > 1 && $page === 0) {
            return response()->view('sitemap.index', compact('pageCount'))->header('Content-Type', 'application/xml; charset=UTF-8');
        }
        $offset = (max(1, $page) - 1) * $perPage;
        $pages = $static->slice($offset, $perPage)->values();
        $offset = max(0, $offset - $static->count());
        foreach ($segments as $index => [$query, $map]) {
            if ($pages->count() >= $perPage) {
                break;
            }
            if ($offset >= $counts[$index]) {
                $offset -= $counts[$index];
                continue;
            }
            $pages = $pages->concat((clone $query)->orderBy('id')->offset($offset)->limit($perPage - $pages->count())->get()->map($map));
            $offset = 0;
        }

        return response()->view('sitemap.xml', ['pages' => $pages, 'workshops' => collect()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
