<x-layout>
    <x-mast backRoute="admin.user.index" backTitle="Admin">Courses</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <x-ui.button href="{{ route('admin.course.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($classSessions->isEmpty())
            <x-none-found item="courses" search="{{ $search }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Course</th>
                    <th>Status</th>
                    <th class="hidden lg:table-cell">Students</th>
                    <th class="hidden md:table-cell">Starts / ends</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($classSessions as $classSession)
                        @php
                            $studentCount = (int) ($classSession->student_count ?? 0);
                            $status = $classSession->adminListStatus();
                            $statusClasses = [
                                'active' => 'bg-emerald-100 text-emerald-800',
                                'pending' => 'bg-amber-100 text-amber-800',
                                'ended' => 'bg-slate-100 text-slate-700',
                            ][$status] ?? 'bg-gray-100 text-gray-700';
                            $deleteWarning = $studentCount > 0
                                ? 'This course has '.$studentCount.' Enroled student'.($studentCount === 1 ? '' : 's').'. Deleting it will remove their access immediately. Any linked forum category will not be deleted automatically, and refunds for paid students must be handled separately.'
                                : 'Are you sure you want to delete this course? This cannot be undone.';
                        @endphp
                        <tr>
                            <td>
                                <div class="font-semibold text-gray-900">{{ $classSession->title }}</div>
                            </td>
                            <td class="text-center">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClasses }}">
                                    {{ $classSession->adminListStatusLabel() }}
                                </span>
                            </td>
                            <td class="hidden lg:table-cell text-center">
                                <div class="text-sm font-semibold text-gray-900">{{ $studentCount }}</div>
                            </td>
                            <td class="hidden md:table-cell">
                                {{ $classSession->adminListScheduleLabel() }}
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-4 whitespace-nowrap">
                                    <a href="{{ route('class.show', $classSession) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-primary-color" title="Open course" aria-label="Open course">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <a href="{{ route('admin.course.edit', $classSession) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-primary-color" title="Edit course" aria-label="Edit course">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="{{ route('admin.course.duplicate', $classSession) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-primary-color" title="Duplicate course" aria-label="Duplicate course">
                                        <i class="fa-regular fa-copy"></i>
                                    </a>
                                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-red-600" title="Delete course" aria-label="Delete course" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete course?', @js($deleteWarning), '{{ route('admin.course.destroy', $classSession) }}')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
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
