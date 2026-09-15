<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\Workshop;
use App\Services\SiteListControls;
use App\Services\StoreCartService;
use App\Support\ListPageSize;
use App\Support\ShopAvailability;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function __construct(private readonly ShopAvailability $shopAvailability) {}

    public function index(Request $request, StoreCartService $cart)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'include_products' => ['sometimes', 'boolean'],
            'include_workshops' => ['sometimes', 'boolean'],
        ]);
        $searchProducts = $request->boolean('include_products', true);
        $searchWorkshops = $request->boolean('include_workshops', true);
        $searchScopeError = ! $searchProducts && ! $searchWorkshops;
        // Old per-section search boxes no longer refine the shared search term.
        $request->query->remove('search_products_search');
        $request->query->remove('search_workshops_search');
        $request->query->remove('search_products_title');
        $request->query->remove('search_workshops_title');
        $search = trim((string) ($validated['q'] ?? ''));
        $searchWords = collect(preg_split('/\s+/', $search) ?: [])
            ->map(fn ($word) => trim((string) $word))
            ->filter()
            ->values();

        $workshops = $searchWorkshops ? $this->searchWorkshops($searchWords) : null;
        $storeSearchEnabled = $this->shopAvailability->isPublicEnabled();
        $products = $storeSearchEnabled && $searchProducts ? $this->searchProducts($searchWords) : null;

        return view('search', [
            'searchProducts' => $searchProducts,
            'searchWorkshops' => $searchWorkshops,
            'searchScopeError' => $searchScopeError,
            'workshops' => $workshops,
            'products' => $products,
            'bestSellerProductIds' => Product::bestSellerIds(),
            'search' => $search,
            'storeSearchEnabled' => $storeSearchEnabled,
            'cartPayload' => $cart->payload([
                'shipping_country' => 'Australia',
                'user' => $request->user(),
            ]),
        ]);
    }

    private function searchWorkshops(Collection $searchWords): LengthAwarePaginator
    {
        $workshopQuery = Workshop::query()
            ->publiclyVisible()
            ->withCount([
                'tickets as active_tickets_count' => fn ($query) => $query->whereIn('status', Ticket::activePurchasedStatuses()),
            ]);

        if ($searchWords->isEmpty()) {
            return $workshopQuery
                ->whereRaw('1 = 0')
                ->pipe(fn ($query) => (new SiteListControls('search_workshops'))->reportQuery($query))->paginate(ListPageSize::resolve(6, 'workshop'), ['*'], 'workshop')
                ->onEachSide(1);
        }

        $workshopQuery->where(function ($query) use ($searchWords): void {
            foreach ($searchWords as $word) {
                $query->orWhere(function ($subQuery) use ($word): void {
                    $subQuery->where('title', 'like', '%'.$word.'%')
                        ->orWhere('content', 'like', '%'.$word.'%')
                        ->orWhereHas('location', function ($locationQuery) use ($word): void {
                            $locationQuery->where('name', 'like', '%'.$word.'%');
                        });
                });
            }
        });

        return $workshopQuery->orderBy('starts_at', 'desc')
            ->pipe(fn ($query) => (new SiteListControls('search_workshops'))->reportQuery($query))->paginate(ListPageSize::resolve(6, 'workshop'), ['*'], 'workshop')
            ->onEachSide(1);
    }

    private function searchProducts(Collection $searchWords): LengthAwarePaginator
    {
        $productQuery = Product::query()
            ->active()
            ->with(['hero', 'categories', 'variants' => fn ($query) => $query->where('is_active', true)]);

        if ($searchWords->isEmpty()) {
            return $productQuery
                ->whereRaw('1 = 0')
                ->pipe(fn ($query) => (new SiteListControls('search_products'))->reportQuery($query))->paginate(ListPageSize::resolve(6, 'product'), ['*'], 'product')
                ->onEachSide(1);
        }

        $productQuery->where(function ($query) use ($searchWords): void {
            foreach ($searchWords as $word) {
                $query->orWhere(function ($subQuery) use ($word): void {
                    $subQuery->where('title', 'like', '%'.$word.'%')
                        ->orWhere('subtitle', 'like', '%'.$word.'%')
                        ->orWhere('category', 'like', '%'.$word.'%')
                        ->orWhere('short_description', 'like', '%'.$word.'%')
                        ->orWhere('description', 'like', '%'.$word.'%')
                        ->orWhere('search_terms', 'like', '%'.$word.'%')
                        ->orWhere('sku', 'like', '%'.$word.'%')
                        ->orWhereHas('categories', fn ($categoryQuery) => $categoryQuery
                            ->where('name', 'like', '%'.$word.'%')
                            ->orWhere('slug', 'like', '%'.$word.'%'))
                        ->orWhereHas('variants', function ($variantQuery) use ($word): void {
                            $variantQuery->where('is_active', true)->where(function ($variantSearch) use ($word): void {
                                $variantSearch->where('name', 'like', '%'.$word.'%')
                                    ->orWhere('sku', 'like', '%'.$word.'%');
                            });
                        });
                });
            }
        });

        return $productQuery->orderBy('title')
            ->pipe(fn ($query) => (new SiteListControls('search_products'))->reportQuery($query))->paginate(ListPageSize::resolve(6, 'product'), ['*'], 'product')
            ->onEachSide(1);
    }
}
