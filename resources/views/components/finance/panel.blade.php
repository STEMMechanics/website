@props(['title'])
<section {{ $attributes->class(['rounded-2xl border border-slate-200 bg-white p-5 min-w-0']) }}>
    @isset($actions)
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold">{{ $title }}</h2>
            <div class="shrink-0">{{ $actions }}</div>
        </div>
    @else
        <h2 class="mb-4 text-xl font-semibold">{{ $title }}</h2>
    @endisset
    {{ $slot }}
</section>
