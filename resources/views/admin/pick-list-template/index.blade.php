<x-layout>
    <x-mast title="Workshop Blueprints" description="Reusable workshop details, hero images, materials, run sheets, and social tasks.">
        <x-slot:actions><x-ui.button color="mast" href="{{ route('admin.workshop-blueprint.create') }}">Create blueprint</x-ui.button></x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="admin-pick-list-template-index">

        <x-ui.collection-controls class="my-5" />

        @if($templates->isEmpty())
            <x-none-found item="workshop blueprints" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing" mobileCards>
                <thead>
                    <tr>
                        <x-ui.list-heading label="Blueprint" />
                        <x-ui.list-heading label="Tasks" class="text-center" />
                        <x-ui.list-heading label="Pick list" class="text-center" />
                        <x-ui.list-heading label="Attachments" class="text-center" />
                        <x-ui.list-heading label="Setup" />
                        <th class="text-center whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $template)
                        @php
                            $taskCount = (int) ($template->tasks_count ?? 0);
                            $materialCount = (int) ($template->items_count ?? 0);
                            $attachmentCount = (int) ($template->attachments_count ?? 0);
                        @endphp
                        <tr>
                            <td data-mobile-primary>
                                <div class="flex min-w-0 items-center gap-3">
                                    @if($template->hero?->thumbnail)
                                        <img src="{{ $template->hero->thumbnail }}" alt="" class="h-14 w-14 shrink-0 rounded-lg bg-slate-100 object-cover" />
                                    @else
                                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400" role="img" aria-label="No hero image" title="No hero image"><i class="fa-regular fa-image" aria-hidden="true"></i></span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.workshop-blueprint.edit', $template) }}" class="font-semibold text-gray-900 hover:text-primary-color">{{ $template->name }}</a>
                                        @if(filled($template->default_workshop_title))
                                            <div class="mt-1 text-sm text-slate-600">Workshop: {{ $template->default_workshop_title }}</div>
                                        @endif
                                        @if(trim((string) ($template->description ?? '')) !== '')
                                            <div class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $template->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Tasks" class="text-center tabular-nums">{{ $taskCount }}</td>
                            <td data-label="Pick list" class="text-center tabular-nums">{{ $materialCount }}</td>
                            <td data-label="Attachments" class="text-center tabular-nums">{{ $attachmentCount }}</td>
                            <td data-label="Setup">
                                <div class="font-medium text-slate-800">{{ $template->duration ?: '—' }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $template->participants ?: 'Participants not set' }}</div>
                            </td>
                            <td data-mobile-actions class="text-center whitespace-nowrap">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.workshop-blueprint.edit', $template) }}" />
                                    <form method="POST" action="{{ route('admin.workshop-blueprint.duplicate', $template) }}" class="inline">
                                        @csrf
                                        <x-ui.row-action label="Duplicate" icon="fa-regular fa-copy" tone="neutral" type="submit" />
                                    </form>
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete blueprint?', 'Are you sure you want to delete this workshop blueprint?', '{{ route('admin.workshop-blueprint.destroy', $template) }}')" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$templates" label="blueprints" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
