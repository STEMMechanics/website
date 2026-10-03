<x-layout>
    <x-container class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl">
            <h1 class="text-3xl font-bold text-gray-900">My sponsorships</h1>
            @forelse($sponsors as $sponsor)
                <div class="mt-8"><x-sponsorship-manage-panel :sponsor="$sponsor" /></div>
            @empty
                <p class="mt-5 rounded-lg bg-gray-50 p-6 text-gray-600">No sponsorships are linked to your account yet. <a class="font-medium text-primary-color hover:underline" href="{{ route('sponsor.index') }}">Explore ways to support STEMMechanics.</a></p>
            @endforelse
        </div>
    </x-container>
</x-layout>
