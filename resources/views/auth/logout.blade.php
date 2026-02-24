<x-layout>
    <x-mast>Log Out</x-mast>
    <x-container class="mt-6 max-w-xl">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-700 mb-4">Confirm logout from your account.</p>
            <form method="POST" action="{{ route('logout') }}" class="flex gap-3">
                @csrf
                <x-ui.button type="submit" color="danger">Log Out</x-ui.button>
                <x-ui.button type="link" color="outline" href="{{ route('index') }}">Cancel</x-ui.button>
            </form>
        </div>
    </x-container>
</x-layout>
