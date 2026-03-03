@php
    $tabs = [
        ['title' => 'Overview', 'route' => route('stemcraft.index')],
        ['title' => 'Join', 'route' => route('stemcraft.join')],
        ['title' => 'Rules', 'route' => route('stemcraft.rules')],
        ['title' => 'FAQs', 'route' => route('stemcraft.faqs')],
        ['title' => 'Punishments', 'route' => route('stemcraft.punishments')],
    ];
@endphp

<x-layout>
    <x-mast image="/stemcraft-short-logo.webp" :tabs="$tabs">Punishments</x-mast>

    <x-container inner-class="max-w-6xl" class="py-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_22rem]">
            <section class="rounded-3xl border border-yellow-200 bg-yellow-50 p-6 shadow-sm xl:order-2">
                <h2 class="text-lg font-semibold text-gray-900">Before you read this page</h2>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    This is a moderation record, not a scoreboard. It exists to make decisions more visible and to reduce confusion around active restrictions.
                </p>
            </section>
            <section class="rounded-3xl border border-gray-200 bg-gray-50 p-6 shadow-sm sm:p-8 xl:order-1">
                <form method="GET" action="{{ route('stemcraft.punishments') }}">
                    <div class="grid gap-4 md:grid-cols-4 items-center">
                        <x-ui.input name="search" label="Search" value="{{ $search }}" />
                        <x-ui.select name="type" label="Type">
                            <option value="">All Types</option>
                            @foreach(\App\Models\MinecraftPenalty::TYPES as $type)
                                <option value="{{ $type }}" {{ $selectedType === $type ? 'selected' : '' }}>{{ strtoupper($type) }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select name="status" label="Status">
                            <option value="">All Statuses</option>
                            <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="lifted" {{ $selectedStatus === 'lifted' ? 'selected' : '' }}>Lifted</option>
                            <option value="expired" {{ $selectedStatus === 'expired' ? 'selected' : '' }}>Expired</option>
                        </x-ui.select>
                        <x-ui.button type="submit" class="mt-2">Filter</x-ui.button>
                    </div>
                </form>
            </section>
        </div>

        @if($penalties->isEmpty())
            <section class="mt-6 rounded-3xl border border-dashed border-gray-300 bg-white p-6">
                <h3 class="text-lg font-semibold text-gray-900">No punishments found</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">There are no punishment records matching the current filters.</p>
            </section>
        @else
            <div class="mt-6 space-y-4 md:hidden">
                @foreach($penalties as $penalty)
                    @php
                        $status = 'Expired';
                        if ($penalty->lifted_at) {
                            $status = 'Lifted';
                        } elseif ($penalty->is_permanent) {
                            $status = 'Permanent';
                        } elseif ($penalty->ends_at && $penalty->ends_at->isFuture()) {
                            $status = 'Active';
                        } elseif ($penalty->type === \App\Models\MinecraftPenalty::TYPE_KICK) {
                            $status = 'Recorded';
                        }
                    @endphp
                    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $penalty->username }}</h3>
                            <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700">{{ $penalty->type }}</span>
                        </div>
                        <div class="mt-2 text-xs font-mono text-gray-500">{{ $penalty->uuid ?: '-' }}</div>
                        @if($penalty->reason)
                            <div class="mt-4 text-sm text-gray-700">{{ $penalty->reason }}</div>
                        @endif
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Started</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $status }}</div>
                                @if($penalty->lifted_at)
                                    <div class="mt-1 text-xs text-gray-500">Lifted {{ $penalty->lifted_at->format('j M Y g:i a') }}</div>
                                @elseif($penalty->ends_at)
                                    <div class="mt-1 text-xs text-gray-500">Until {{ $penalty->ends_at->format('j M Y g:i a') }}</div>
                                @endif
                            </div>
                        </div>
                        @if($penalty->by_username)
                            <div class="mt-3 text-xs text-gray-500">By {{ $penalty->by_username }}</div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="mt-6 hidden md:block">
                <x-ui.table>
                    <x-slot:header>
                        <th>Player</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th class="hidden lg:table-cell">Started</th>
                        <th>Status</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($penalties as $penalty)
                            @php
                                $status = 'Expired';
                                if ($penalty->lifted_at) {
                                    $status = 'Lifted';
                                } elseif ($penalty->is_permanent) {
                                    $status = 'Permanent';
                                } elseif ($penalty->ends_at && $penalty->ends_at->isFuture()) {
                                    $status = 'Active';
                                } elseif ($penalty->type === \App\Models\MinecraftPenalty::TYPE_KICK) {
                                    $status = 'Recorded';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-semibold">{{ $penalty->username }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $penalty->uuid }}</div>
                                    @if($penalty->by_username)
                                        <div class="text-xs text-gray-500 mt-1">By {{ $penalty->by_username }}</div>
                                    @endif
                                </td>
                                <td class="uppercase">{{ $penalty->type }}</td>
                                <td>{{ $penalty->reason ?: '-' }}</td>
                                <td class="hidden lg:table-cell">{{ $penalty->started_at?->format('j M Y g:i a') ?? '-' }}</td>
                                <td>
                                    <div>{{ $status }}</div>
                                    @if($penalty->lifted_at)
                                        <div class="text-xs text-gray-500">Lifted {{ $penalty->lifted_at->format('j M Y g:i a') }}</div>
                                    @elseif($penalty->ends_at)
                                        <div class="text-xs text-gray-500">Until {{ $penalty->ends_at->format('j M Y g:i a') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            <div class="mt-6">
                {{ $penalties->appends(request()->query())->links() }}
            </div>
        @endif
    </x-container>
</x-layout>
