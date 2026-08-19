<x-layout>
    <x-mast backRoute="admin.subscription.index" backTitle="Email Subscriptions">Subscription Store Themes</x-mast>
    <x-container class="mt-4">
        <x-ui.toolbar>
            <x-slot:left><x-ui.button href="{{ route('admin.subscription.theme.create') }}">Create Theme</x-ui.button></x-slot:left>
        </x-ui.toolbar>
        @if($themes->isEmpty())
            <x-none-found item="store themes" />
        @else
            <x-ui.table>
                <x-slot:header><th>Theme</th><th class="hidden md:table-cell">Categories</th><th class="hidden md:table-cell">Match</th><th>Status</th><th>Action</th></x-slot:header>
                <x-slot:body>
                    @foreach($themes as $theme)
                        <tr>
                            <td><div class="font-semibold text-gray-900">{{ $theme->name }}</div><div class="text-xs text-gray-500">{{ $theme->title }}</div></td>
                            <td class="hidden md:table-cell">{{ collect($theme->category_slugs)->map(fn ($slug) => ucfirst($slug))->join(', ') }}</td>
                            <td class="hidden md:table-cell">{{ $theme->matchLabel() }}</td>
                            <td><x-ui.badge :color="$theme->is_active ? 'success' : 'secondary'">{{ $theme->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                            <td><a href="{{ route('admin.subscription.theme.edit', $theme) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a></td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
    </x-container>
</x-layout>
