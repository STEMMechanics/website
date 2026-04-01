@php
    $classSessions = collect($classSessions ?? []);
    $currentSessions = $classSessions->where('status', 'current');
    $upcomingSessions = $classSessions->where('status', 'upcoming');
    $pastSessions = $classSessions->where('status', 'past');
@endphp

    <x-layout>
    <x-mast description="You can find all the courses you are enrolled in on this page.">Courses</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-3xl border border-slate-200 bg-white px-6 pb-6 shadow-sm sm:px-8 sm:pb-8">
                @if(!$hasClassrooms)
                    <h2 class="text-lg font-semibold text-gray-900 mt-8">No courses yet</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        If you have enrolled in a course or workshop group, it will show up here.
                    </p>
                @else
                    @if($currentSessions->isNotEmpty())
                        <section class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900">Active courses</h2>
                            <div class="mt-4 space-y-4">
                                @foreach($currentSessions as $entry)
                                    @php($classSession = $entry['classSession'])
                                    <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <h3 class="text-xl font-semibold text-gray-900">{{ $classSession->title }}</h3>
                                                    @if((int) ($entry['forumUnreadCount'] ?? 0) > 0)
                                                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" aria-label="{{ (int) $entry['forumUnreadCount'] }} unread discussion notifications">{{ number_format((int) $entry['forumUnreadCount']) }}</span>
                                                    @endif
                                                </div>
                                                @if($classSession->summary)
                                                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $classSession->summary }}</p>
                                                @endif
                                                <div class="mt-3 flex flex-col gap-1 text-xs text-gray-600">
                                                    @if($classSession->starts_at)
                                                        <span><span class="font-semibold w-10 inline-block">Starts</span> {{ $classSession->starts_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                    @if($classSession->ends_at)
                                                            <span><span class="font-semibold w-10 inline-block">Ends</span> {{ $classSession->ends_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-wrap gap-2">
                                                <x-ui.button href="{{ $entry['openUrl'] }}" color="primary">Open course</x-ui.button>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if($upcomingSessions->isNotEmpty())
                        <section class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900">Upcoming courses</h2>
                            <div class="mt-4 space-y-4">
                                @foreach($upcomingSessions as $entry)
                                    @php($classSession = $entry['classSession'])
                                    <article class="rounded-3xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <h3 class="text-xl font-semibold text-gray-900">{{ $classSession->title }}</h3>
                                                    @if((int) ($entry['forumUnreadCount'] ?? 0) > 0)
                                                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" aria-label="{{ (int) $entry['forumUnreadCount'] }} unread discussion notifications">{{ number_format((int) $entry['forumUnreadCount']) }}</span>
                                                    @endif
                                                </div>
                                                @if($classSession->summary)
                                                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $classSession->summary }}</p>
                                                @endif
                                                <div class="mt-3 flex flex-col gap-1 text-xs text-gray-600">
                                                    @if($classSession->starts_at)
                                                        <span><span class="font-semibold w-10 inline-block">Starts</span> {{ $classSession->starts_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-wrap gap-2">
                                                <x-ui.button href="{{ $entry['openUrl'] }}" color="primary-outline">View course</x-ui.button>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if($pastSessions->isNotEmpty())
                        <section class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900">Past sessions</h2>
                            <div class="mt-4 space-y-4">
                                @foreach($pastSessions as $entry)
                                    @php($classSession = $entry['classSession'])
                                    <article class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <h3 class="text-xl font-semibold text-gray-900">{{ $classSession->title }}</h3>
                                                    @if((int) ($entry['forumUnreadCount'] ?? 0) > 0)
                                                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" aria-label="{{ (int) $entry['forumUnreadCount'] }} unread discussion notifications">{{ number_format((int) $entry['forumUnreadCount']) }}</span>
                                                    @endif
                                                </div>
                                                <p class="mt-2 text-sm leading-6 text-gray-700">{{ $classSession->summary ?: 'No summary has been added yet.' }}</p>
                                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-600">
                                                    @if($classSession->starts_at)
                                                        <span>Started {{ $classSession->starts_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                    @if($classSession->ends_at)
                                                        <span>Ended {{ $classSession->ends_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-wrap gap-2">
                                                <x-ui.button href="{{ $entry['openUrl'] }}" color="secondary">Open anyway</x-ui.button>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif
            </section>
        </div>
    </x-container>
</x-layout>
