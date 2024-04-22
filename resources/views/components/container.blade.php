<div class="{{ twMerge('flex justify-center px-4', ($attributes->get('class') ?? '')) }}">
    <div class="{{ twMerge('max-w-7xl w-full', ($attributes->get('inner-class') ?? '')) }}">{{ $slot }}</div>
</div>
