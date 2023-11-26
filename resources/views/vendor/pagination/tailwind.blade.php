@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span
                class="relative inline-flex cursor-default items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-500">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                class="focus:border-blue-300 relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-700 ring-gray-300 transition duration-150 ease-in-out hover:text-gray-500 focus:outline-none focus:ring active:bg-gray-100 active:text-gray-700">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        <div>
            <p class="text-xs leading-5 text-gray-500">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-medium">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-medium">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-medium">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>
        </div>


        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                class="focus:border-blue-300 relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-700 ring-gray-300 transition duration-150 ease-in-out hover:text-gray-500 focus:outline-none focus:ring active:bg-gray-100 active:text-gray-700">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span
                class="relative inline-flex cursor-default items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-500">
                {!! __('pagination.next') !!}
            </span>
        @endif
    </nav>
@endif
