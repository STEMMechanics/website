@php($onlineCount = app(\App\Services\OnlineVisitors::class)->count())
<a href="{{ route('admin.analytics.visitors') }}" {{ $attributes->class(['rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-white transition hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white']) }} data-online-visitors data-url="{{ route('admin.analytics.online') }}" aria-label="Online visitors">
    <div class="flex flex-col items-center">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <i class="fa-solid fa-users text-white/80" aria-hidden="true"></i>
            <span class="text-sm font-semibold text-white">Online visitors</span>
            <span data-online-count class="text-2xl font-bold tabular-nums text-white">{{ $onlineCount === null ? 'Unavailable' : number_format($onlineCount) }}</span>
        </div>
        <span data-online-error class="text-xs text-white/90" hidden>Unable to refresh</span>
    </div>
</a>
