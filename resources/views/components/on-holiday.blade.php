@props(['item' => 'results', 'search', 'message', 'title'])

@php
if(!isset($message)) {
    if(!isset($search) || $search == '')
        $message = "We couldn't find any $item";
    else
        $message = "We couldn't find any $item matching \"$search\"";
}

if(!isset($title)) {
    $title = "No results found";
}
@endphp

<div class="flex flex-col items-center my-8 w-full">
    <i class="text-gray-300 mb-6 text-8xl fa-solid fa-cat"></i>
    <p class="text-gray-500 mt-2">No workshops coming up. We’re on a short break, mostly by playing with the cat.</p>
</div>
