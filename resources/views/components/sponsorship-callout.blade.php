@props(['source' => 'website'])
<aside class="my-8 rounded-2xl border border-primary-color/20 bg-slate-50 p-6">
    <x-ui.badge color="primary" variant="solid">Like what we’re building?</x-ui.badge>
    <h2 class="mt-3 text-xl font-semibold text-gray-900">Support STEMMechanics</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">Sponsorship helps fund community workshops, open-source development, testing and infrastructure while supporting the wider work STEMMechanics does.</p>
    <x-ui.button href="{{ route('sponsor.index', ['ref' => $source]) }}" class="mt-4">Sponsor our work</x-ui.button>
</aside>
