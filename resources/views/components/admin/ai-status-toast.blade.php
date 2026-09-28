@props(['id', 'message', 'detail', 'progressLabel' => 'AI drafting progress'])

<div id="{{ $id }}" popover="manual" class="sm-ai-status-toast pointer-events-none fixed left-4 right-4 top-4 z-[3200] mx-auto max-w-md -translate-y-full opacity-0 transition-all duration-300 ease-out" data-ai-widget data-ai-toast aria-hidden="true">
    <div data-ai-status class="overflow-hidden rounded-xl border border-sky-200 bg-white text-sm font-medium shadow-lg" role="status" aria-live="polite">
        <div class="sm-ai-status-row relative flex min-h-14 items-center gap-3 px-4 py-3">
            <span class="sm-ai-status-icon" aria-hidden="true">
                <i data-ai-processing-icon class="fa-solid fa-wand-magic-sparkles"></i>
                <i data-ai-complete-icon class="fa-solid fa-circle-check"></i>
                <i data-ai-error-icon class="fa-solid fa-circle-exclamation"></i>
            </span>
            <span class="sm-ai-starfield" aria-hidden="true">
                <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-1"></i>
                <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-2"></i>
                <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-3"></i>
                <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-4"></i>
                <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-5"></i>
                <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-6"></i>
            </span>
            <span class="sm-ai-status-copy">
                <span data-ai-status-text class="block">{{ $message }}</span>
                <span data-ai-status-detail class="mt-0.5 block text-xs font-normal text-slate-500" hidden>{{ $detail }}</span>
            </span>
        </div>
        <div data-ai-progress-track class="sm-ai-progress-track" role="progressbar" aria-label="{{ $progressLabel }}" aria-valuetext="Processing" aria-hidden="true">
            <span class="sm-ai-progress-bar"></span>
        </div>
    </div>
</div>
