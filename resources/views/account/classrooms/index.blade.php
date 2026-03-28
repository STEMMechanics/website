@php
    $classSessions = collect($classSessions ?? []);
    $currentSessions = $classSessions->where('status', 'current');
    $upcomingSessions = $classSessions->where('status', 'upcoming');
    $pastSessions = $classSessions->where('status', 'past');
@endphp

<x-layout>
    <x-mast description="Open the classrooms you are enrolled in or have access to through your workshop group.">Classrooms</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full bg-primary-color-light px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">
                        Your classrooms
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold text-gray-900">Open the sessions you're part of</h1>
                    <p class="mt-3 text-base leading-7 text-gray-600">
                        Classrooms appear here when you are enrolled directly or included through a workshop access group. Open a classroom to join the live room, view instructions, and follow the session notes.
                    </p>
                </div>

                @if(! $hasClassrooms)
                    <div class="mt-8 rounded-3xl border border-dashed border-gray-300 bg-gray-50 p-6">
                        <h2 class="text-lg font-semibold text-gray-900">No classrooms yet</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            If your teacher has added you to a classroom or workshop group, it will show up here after you sign in.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <x-ui.button href="{{ route('stemcraft.join') }}" color="primary-outline">How STEMCraft access works</x-ui.button>
                            <x-ui.button href="{{ route('account.stemcraft.index') }}" color="secondary">My STEMCraft accounts</x-ui.button>
                        </div>
                    </div>
                @else
                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $currentCount }}</div>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Upcoming</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $upcomingCount }}</div>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Past</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $pastCount }}</div>
                        </div>
                    </div>

                    @if($currentSessions->isNotEmpty())
                        <section class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900">Current sessions</h2>
                            <div class="mt-4 space-y-4">
                                @foreach($currentSessions as $entry)
                                    @php($classSession = $entry['classSession'])
                                    <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-xl font-semibold text-gray-900">{{ $classSession->title }}</h3>
                                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $entry['statusClass'] }}">{{ $entry['statusLabel'] }}</span>
                                                    <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $entry['badgeLabel'] }}</span>
                                                </div>
                                                <p class="mt-2 text-sm leading-6 text-gray-700">{{ $classSession->summary ?: 'No summary has been added yet.' }}</p>
                                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-600">
                                                    @if($classSession->starts_at)
                                                        <span>Starts {{ $classSession->starts_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                    @if($classSession->ends_at)
                                                        <span>Ends {{ $classSession->ends_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                    @if($classSession->access_group_slug)
                                                        <span>Group {{ $classSession->access_group_slug }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-wrap gap-2">
                                                <x-ui.button href="{{ $entry['openUrl'] }}" color="primary">Open classroom</x-ui.button>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if($upcomingSessions->isNotEmpty())
                        <section class="mt-8">
                            <h2 class="text-lg font-semibold text-gray-900">Upcoming sessions</h2>
                            <div class="mt-4 space-y-4">
                                @foreach($upcomingSessions as $entry)
                                    @php($classSession = $entry['classSession'])
                                    <article class="rounded-3xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-xl font-semibold text-gray-900">{{ $classSession->title }}</h3>
                                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $entry['statusClass'] }}">{{ $entry['statusLabel'] }}</span>
                                                    <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $entry['badgeLabel'] }}</span>
                                                </div>
                                                <p class="mt-2 text-sm leading-6 text-gray-700">{{ $classSession->summary ?: 'No summary has been added yet.' }}</p>
                                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-600">
                                                    @if($classSession->starts_at)
                                                        <span>Starts {{ $classSession->starts_at->format('j M Y g:i a') }}</span>
                                                    @endif
                                                    @if($classSession->access_group_slug)
                                                        <span>Group {{ $classSession->access_group_slug }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-wrap gap-2">
                                                <x-ui.button href="{{ $entry['openUrl'] }}" color="primary-outline">View classroom</x-ui.button>
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
                                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-xl font-semibold text-gray-900">{{ $classSession->title }}</h3>
                                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $entry['statusClass'] }}">{{ $entry['statusLabel'] }}</span>
                                                    <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $entry['badgeLabel'] }}</span>
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

            <aside class="space-y-4">
                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Access</div>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Classrooms are linked to your website account through enrolments or workshop access groups. If your teacher adds a new term, it will appear here automatically after you log in.
                    </p>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Need help?</div>
                    <div class="mt-3 space-y-3">
                        <x-ui.button href="{{ route('stemcraft.join') }}" color="primary-outline" class="w-full">Join guide</x-ui.button>
                        <x-ui.button href="{{ route('account.show') }}" color="secondary" class="w-full">Back to account</x-ui.button>
                    </div>
                </section>
            </aside>
        </div>
    </x-container>
</x-layout>
