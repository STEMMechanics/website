<div class="mb-1 flex items-center gap-2 pl-1">
    <label class="block text-sm">{{ $label }}</label>
    <div class="relative" x-data="{ placeholderHelpOpen: false }">
        <button type="button" class="text-sky-700 hover:text-sky-900" x-on:click="placeholderHelpOpen = !placeholderHelpOpen" title="Show workshop placeholders" aria-label="Show workshop placeholders"><i class="fa-solid fa-circle-info"></i></button>
        <div x-show="placeholderHelpOpen" x-cloak x-on:click.outside="placeholderHelpOpen = false" class="absolute left-0 top-7 z-20 w-[min(34rem,calc(100vw-3rem))] rounded-xl border border-sky-200 bg-white p-4 text-xs text-gray-700 shadow-xl">
            <div class="mb-3 flex items-center justify-between gap-3"><p class="text-sm font-semibold text-gray-900">Workshop placeholders</p><button type="button" class="text-gray-500 hover:text-gray-800" x-on:click="placeholderHelpOpen = false" aria-label="Close placeholder help"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2">
                <code>{date-short}</code><span>27/08/2026</span>
                <code>{date-long}</code><span>Thursday 27 August</span>
                <code>{date-ddd dd/mm/yyyy}</code><span>Thu 27/08/2026</span>
                <code>{start-time}</code><span>3:30pm</span>
                <code>{end-time}</code><span>4:30pm</span>
                <code>{time-range}</code><span>3:30-4:30pm</span>
                <code>{location}</code><span>Innovation Centre</span>
                <code>{ages}</code><span>8–12</span>
                <code>{cost}</code><span>$25.00 or Free</span>
                <code>{workshop-url}</code><span>Public workshop page URL</span>
            </div>
            <p class="mt-3 border-t border-gray-100 pt-3 text-gray-500">Custom dates support d, dd, ddd, dddd, m, mm, mmm, mmmm, yy, and yyyy.</p>
        </div>
    </div>
</div>
