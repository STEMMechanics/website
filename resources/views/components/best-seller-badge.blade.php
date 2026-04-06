@props([
    'label' => 'Best seller',
])

<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full bg-amber-400/95 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-950 shadow-sm ring-1 ring-amber-200/70') }}>
    <i class="fa-solid fa-trophy text-[0.85em]"></i>
    <span>{{ $label }}</span>
</span>
