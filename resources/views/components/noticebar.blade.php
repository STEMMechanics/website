<div x-data="{show:$persist(true).using(sessionStorage)}" x-show="show" class="bg-yellow-200 text-sm text-center pl-3 pr-8 py-2 relative shadow" x-cloak>
{{ $slot }}
<i class="fa-solid fa-close absolute right-4 top-2.5 hover:text-red-500 cursor-pointer" x-on:click="show=false"></i>
</div>
