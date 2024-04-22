@props(['image'])

<div class="{{twMerge('relative w-full h-96 flex items-center justify-center bg-cover bg-center rounded-3xl overflow-hidden', $attributes->get('class'))}}">
    <div class="blur bg-cover bg-center absolute top-0 left-0 w-full h-full opacity-50" style="background-image: url('{{ $image }}')"></div>
    <img src="{{ $image }}" class="h-full z-0" />
</div>
