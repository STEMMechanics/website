@props(['title'])
<section {{ $attributes->class(['rounded-2xl border border-slate-200 bg-white p-5 min-w-0']) }}>
    <h2 class="mb-4 text-xl font-semibold">{{ $title }}</h2>
    {{ $slot }}
</section>
