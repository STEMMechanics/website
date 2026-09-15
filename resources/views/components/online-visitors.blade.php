@php($onlineCount = app(\App\Services\OnlineVisitors::class)->count())
<section {{ $attributes->class(['rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-white']) }} data-online-visitors data-url="{{ route('admin.analytics.online') }}" aria-label="Online visitors">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <i class="fa-solid fa-users text-white/80" aria-hidden="true"></i>
        <h2 class="text-sm font-semibold text-white">Online visitors</h2>
        <span data-online-count class="text-2xl font-bold tabular-nums text-white">{{ $onlineCount === null ? 'Unavailable' : number_format($onlineCount) }}</span>
        <span data-online-error class="text-xs text-white/90" hidden>Unable to refresh</span>
    </div>
</section>
