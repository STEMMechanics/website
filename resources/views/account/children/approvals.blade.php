@php
    $childApprovalGroups = collect($childApprovalGroups ?? []);
    $pendingApprovalCount = (int) ($pendingApprovalCount ?? 0);
@endphp

<x-layout>
    <x-mast backRoute="account.show" backTitle="Account Settings">
        Child Approvals
    </x-mast>

    <x-container inner-class="max-w-6xl">
        <div class="my-8 space-y-6">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Combined Approval Queue</h2>
                        <p class="mt-1 text-sm text-gray-600">Review pending discussion threads and replies from all child accounts.</p>
                    </div>
                </div>

                @if($childApprovalGroups->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500">
                        There is nothing waiting for approval right now.
                    </div>
                @else
                    <form method="POST" action="{{ route('account.children.approvals.bulk') }}" class="mt-5 space-y-6">
                        @csrf

                        @foreach($childApprovalGroups as $group)
                            @php
                                $child = $group['child'];
                                $items = collect($group['items'] ?? []);
                            @endphp
                            <section id="child-{{ $child->id }}" class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ $child->username }}</h3>
                                        <p class="mt-1 text-sm text-gray-600">Pending discussion approvals for this child account.</p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-4">
                                    @foreach($items as $item)
                                        <div class="flex gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                            <span class="pt-1">
                                                <x-ui.checkbox
                                                    name="selected_items[]"
                                                    :value="$item['selection_key']"
                                                    label="Select approval item"
                                                    labelHidden="true"
                                                    noWrapper="true"
                                                />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex flex-wrap items-start justify-between gap-3">
                                                    <span class="flex flex-wrap items-center gap-2">
                                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xxs font-semibold uppercase tracking-wide text-gray-700">{{ $item['kind'] }}</span>
                                                        <span class="text-xs text-gray-500">{{ $item['category_name'] !== '' ? $item['category_name'].' · ' : '' }}{{ $item['created_at']?->format('j M Y g:i a') ?? '-' }}</span>
                                                    </span>
                                                    <span class="flex items-center gap-2">
                                                        <form method="POST" action="{{ route('account.children.approvals.bulk') }}">
                                                            @csrf
                                                            <input type="hidden" name="action" value="approve" />
                                                            <input type="hidden" name="selected_items[]" value="{{ $item['selection_key'] }}" />
                                                            <button
                                                                type="submit"
                                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-green-700 transition hover:bg-green-200"
                                                                title="Approve item"
                                                                aria-label="Approve item"
                                                            >
                                                                <i class="fa-solid fa-thumbs-up"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('account.children.approvals.bulk') }}">
                                                            @csrf
                                                            <input type="hidden" name="action" value="reject" />
                                                            <input type="hidden" name="selected_items[]" value="{{ $item['selection_key'] }}" />
                                                            <button
                                                                type="submit"
                                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-700 transition hover:bg-red-200"
                                                                title="Discard item"
                                                                aria-label="Discard item"
                                                            >
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </span>
                                                </span>
                                                <span class="mt-2 block text-sm font-semibold text-gray-900">{{ $item['title'] }}</span>
                                                <span class="mt-2 block text-sm text-gray-600">
                                                    @if($item['preview'] !== '')
                                                        {!! nl2br(e($item['preview'])) !!}
                                                    @else
                                                        No preview available.
                                                    @endif
                                                </span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        <div class="flex justify-end gap-3 border-t border-gray-200 pt-2">
                            <x-ui.button type="submit" name="action" value="approve" color="success">Approve selected</x-ui.button>
                            <x-ui.button type="submit" name="action" value="reject" color="danger-outline">Discard selected</x-ui.button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </x-container>
</x-layout>
