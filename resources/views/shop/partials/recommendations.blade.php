@if($products->isNotEmpty())
    <section class="mt-16 border-t border-gray-300 pt-8" aria-labelledby="recommended-products-title">
        <h2 id="recommended-products-title" class="mb-6 text-2xl font-bold text-gray-900">Other products you may like</h2>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $recommendedProduct)
                <a href="{{ route('shop.product.show', $recommendedProduct) }}" class="group flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition-all hover:shadow-lg hover:scale-[101%] motion-reduce:transform-none motion-reduce:transition-none focus-visible:outline-primary-color">
                    <img src="{{ $recommendedProduct->primaryImageUrl('md') }}" alt="{{ $recommendedProduct->title }}" width="768" height="576" loading="lazy" class="aspect-[4/3] w-full object-cover bg-gray-100" />
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <h3 class="text-xl font-bold text-gray-900 group-hover:text-primary-color">{{ $recommendedProduct->title }}</h3>
                        @if($recommendedProduct->subtitle)
                            <p class="text-sm text-gray-600">{{ $recommendedProduct->subtitle }}</p>
                        @endif
                        @if(trim((string) $recommendedProduct->short_description) !== '')
                            <p class="flex-1 text-sm text-gray-600">{{ $recommendedProduct->short_description }}</p>
                        @endif
                        <x-product-card-summary :product="$recommendedProduct" class="mt-auto flex flex-wrap items-end justify-between gap-3" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
