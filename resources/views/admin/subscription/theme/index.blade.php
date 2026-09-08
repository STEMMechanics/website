<x-layout>
@php
    $newsletterTabs = [
        ['title' => 'Newsletter', 'route' => route('admin.newsletter.index'), 'active' => request()->routeIs('admin.newsletter.index')],
        ['title' => 'Themes', 'route' => route('admin.subscription.theme.index'), 'active' => request()->routeIs('admin.subscription.theme.*')],
    ];
@endphp

    <x-mast :tabs="$newsletterTabs" backRoute="admin.newsletter.index" backTitle="Newsletter">Newsletter themes
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.subscription.theme.create') }}">Create Theme</x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="mt-4">
<x-ui.dynamic-list name="admin-subscription-theme-index">
        <x-ui.collection-controls class="my-5" />
        @if($themes->isEmpty())
            <x-none-found item="newsletter themes" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header><x-ui.list-heading field="name" label="Theme" /><x-ui.list-heading class="hidden md:table-cell" label="Categories" /><x-ui.list-heading class="hidden md:table-cell" label="Match" /><x-ui.list-heading field="is_active" class="text-center!" label="Status" /><x-ui.list-heading class="text-center!" label="Actions" /></x-slot:header>
                <x-slot:body>
                    @foreach($themes as $theme)
                        <tr>
                            <td><div class="font-semibold text-gray-900">{{ $theme->name }}</div><div class="text-xs text-gray-500">{{ $theme->title }}</div></td>
                            <td class="hidden md:table-cell">{{ collect($theme->category_slugs)->map(fn ($slug) => ucfirst($slug))->join(', ') }}</td>
                            <td class="hidden md:table-cell">{{ $theme->matchLabel() }}</td>
                            <td class="text-center!"><x-ui.badge :color="$theme->is_active ? 'success' : 'gray'">{{ $theme->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                            <td class="text-center!"><x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.subscription.theme.edit', $theme) }}" /></td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
        @endif
    </x-ui.dynamic-list>
</x-container>
</x-layout>
