<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\Workshop;
use App\Support\ShopAvailability;
use App\Services\StoreCartService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function __construct(private readonly ShopAvailability $shopAvailability)
    {
    }

    public function index(Request $request, StoreCartService $cart)
    {
        $search = trim((string) $request->query('q', ''));
        $searchWords = collect(preg_split('/\s+/', $search) ?: [])
            ->map(fn ($word) => trim((string) $word))
            ->filter()
            ->values();

        $workshops = $this->searchWorkshops($searchWords);
        $storeSearchEnabled = $this->shopAvailability->isPublicEnabled();
        $products = $storeSearchEnabled ? $this->searchProducts($searchWords) : null;

        return view('search', [
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
                ->paginate(6, ['*'], 'workshop')
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
            ->paginate(6, ['*'], 'workshop')
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
                ->paginate(6, ['*'], 'product')
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
            ->paginate(6, ['*'], 'product')
            ->onEachSide(1);
    }
}
