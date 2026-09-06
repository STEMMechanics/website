@props(['count', 'label' => null])
@if((int) $count > 0)
<span {{ $attributes->class(['sm-nav-count']) }} title="{{ $label ?? number_format($count).' items need attention' }}" aria-label="{{ $label ?? number_format($count).' items need attention' }}">{{ (int) $count > 99 ? '99+' : (int) $count }}</span>
@endif
