<x-layout>
    <x-mast backRoute="admin.user.index" backTitle="Admin">Classrooms</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.classroom.create') }}">Create Classroom</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($classSessions->isEmpty())
            <x-none-found item="classrooms" search="{{ $search }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Classroom</th>
                    <th class="hidden md:table-cell">Access</th>
                    <th class="hidden lg:table-cell">Enrolments</th>
                    <th class="hidden lg:table-cell">Help</th>
                    <th class="hidden md:table-cell">Updated</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($classSessions as $classSession)
                        <tr>
                            <td>
                                <div class="font-semibold text-gray-900">{{ $classSession->title }}</div>
                                <div class="text-xs text-gray-500">Slug: {{ $classSession->slug }}</div>
                                <div class="text-xs text-gray-500">Room: <span class="font-mono">{{ $classSession->room_name }}</span></div>
                                <div class="md:hidden mt-1 text-xs text-gray-600">
                                    {{ $classSession->access_group_slug ? 'Group: '.$classSession->access_group_slug : 'No group access' }}
                                </div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div>{{ $classSession->access_group_slug ?: 'Manual enrolments only' }}</div>
                                @if($classSession->forumCategory)
                                    <div class="text-xs text-gray-500">Forum: {{ $classSession->forumCategory->name }}</div>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">
                                <div class="text-sm">
                                    Teachers: <span class="font-semibold">{{ (int) ($classSession->teacher_count ?? 0) }}</span>
                                </div>
                                <div class="text-sm">
                                    Students: <span class="font-semibold">{{ (int) ($classSession->student_count ?? 0) }}</span>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell">
                                <div class="text-sm">
                                    Pending: <span class="font-semibold">{{ (int) ($classSession->pending_help_request_count ?? 0) }}</span>
                                </div>
                                <div class="text-sm">
                                    Active: <span class="font-semibold">{{ (int) ($classSession->active_help_request_count ?? 0) }}</span>
                                </div>
                            </td>
                            <td class="hidden md:table-cell">
                                {{ $classSession->updated_at?->format('j M Y g:i a') ?? '-' }}
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-center gap-3 whitespace-nowrap">
                                    <x-ui.button href="{{ route('class.show', $classSession) }}" color="primary-outline" class="px-4! py-1.5!">Open</x-ui.button>
                                    <x-ui.button href="{{ route('admin.classroom.edit', $classSession) }}" color="primary-outline" class="px-4! py-1.5!">Edit</x-ui.button>
                                    <x-ui.button href="{{ route('admin.classroom.duplicate', $classSession) }}" color="secondary" class="px-4! py-1.5!">Duplicate</x-ui.button>
                                    <x-ui.button type="button" color="danger" class="px-4! py-1.5!" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete classroom?', 'Are you sure you want to delete this classroom? This cannot be undone.', '{{ route('admin.classroom.destroy', $classSession) }}')">Delete</x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $classSessions->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
