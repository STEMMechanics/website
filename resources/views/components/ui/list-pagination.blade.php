@props(['paginator', 'label' => 'items', 'showSummary' => true])
@php
    $paginator->withQueryString();
    $pageSizeName = \App\Support\ListPageSize::parameter($paginator->getPageName());
    $pageSizes = collect([10, 25, 50, 100, $paginator->perPage()])->unique()->sort();
    $pageSizeQuery = request()->except([$paginator->getPageName(), $pageSizeName]);
@endphp
<div data-list-footer class="mt-5 flex flex-wrap items-center {{ $showSummary ? 'justify-between' : 'justify-end' }} gap-4 text-sm text-slate-500">
    @if($showSummary)
    <p>Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }} {{ $label }}</p>
    @endif
    @isset($actions)<div class="min-w-0">{{ $actions }}</div>@endisset
    <div class="ml-auto flex flex-wrap items-center gap-4">
        {{ $slot }}
        <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2 whitespace-nowrap" data-dynamic-form>
            @foreach(\Illuminate\Support\Arr::dot($pageSizeQuery) as $key => $value)
                @php($inputName = preg_replace('/\.([^.]*)/', '[$1]', $key))
                @if(is_scalar($value))<input type="hidden" name="{{ $inputName }}" value="{{ $value }}">@endif
            @endforeach
            <x-ui.select :name="$pageSizeName" label="Show" inline-label class="mb-0" selectClass="min-w-20 pr-9!" onchange="this.form.requestSubmit()" aria-label="Rows per page">
                @foreach($pageSizes as $size)<option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>@endforeach
            </x-ui.select>
            <span>rows</span>
        </form>
        @if($paginator->hasPages())
            <nav data-dynamic-link aria-label="Pagination Navigation" class="flex items-center gap-1">
                @if($paginator->onFirstPage())<span class="sm-page-link opacity-40" aria-disabled="true" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></span>@else<a class="sm-page-link" href="{{ $paginator->previousPageUrl() }}" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>@endif
                @php($pages = collect([1, ...range(max(1, $paginator->currentPage() - 1), min($paginator->lastPage(), $paginator->currentPage() + 1)), $paginator->lastPage()])->unique()->sort()->values())
                @foreach($pages as $index => $page)
                    @if($index > 0 && $page - $pages[$index - 1] > 1)<span class="hidden sm:inline" aria-hidden="true">…</span>@endif
                    @if($page === $paginator->currentPage())<span class="sm-page-link sm-page-current" aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>@else<a class="sm-page-link {{ abs($page - $paginator->currentPage()) > 1 ? 'hidden! sm:inline-flex!' : '' }}" href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>@endif
                @endforeach
                @if($paginator->hasMorePages())<a class="sm-page-link" href="{{ $paginator->nextPageUrl() }}" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>@else<span class="sm-page-link opacity-40" aria-disabled="true" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></span>@endif
            </nav>
        @endif
    </div>
</div>
