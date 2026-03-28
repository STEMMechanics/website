@php
    $childAccounts = collect($childAccounts ?? []);
    $totalPendingChildApprovals = (int) ($totalPendingChildApprovals ?? 0);
@endphp

<x-layout>
    <x-mast description="Create, edit, and review child accounts without leaving the user menu.">Child Accounts</x-mast>

    <x-container inner-class="max-w-6xl">
        <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Managed child accounts</h2>
                        <p class="mt-1 text-sm text-gray-600">Create child accounts for discussion access, workshop access, and parental approval controls.</p>
                    </div>

                    <x-ui.button href="{{ route('account.children.create') }}">Create child account</x-ui.button>
                </div>

                @if($totalPendingChildApprovals > 0)
                    <div class="mt-5 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-4 text-sm text-orange-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-semibold">Pending child approvals</div>
                                <div class="mt-1">{{ $totalPendingChildApprovals }} discussion {{ \Illuminate\Support\Str::plural('submission', $totalPendingChildApprovals) }} {{ $totalPendingChildApprovals === 1 ? 'is' : 'are' }} waiting for review.</div>
                            </div>
                            <x-ui.button href="{{ route('account.children.approvals') }}" class="px-4! py-1.5!">Open approvals queue</x-ui.button>
                        </div>
                    </div>
                @endif

                @if($childAccounts->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-sm text-gray-500">
                        No child accounts have been created yet.
                    </div>
                @else
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach($childAccounts as $childAccount)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ $childAccount->username }}</div>
                                    </div>
                                    <x-ui.button
                                        href="{{ route('account.children.edit', $childAccount) }}"
                                        class="text-xs"
                                    >Manage</x-ui.button>
                                </div>
                                <div class="mt-4 grid gap-2 text-sm text-gray-600">
                                    <div><span class="font-semibold">Can create new threads</span>: {{ $childAccount->child_can_create_forum_topics ? ($childAccount->child_forum_topic_requires_approval ? 'Approval required' : 'Yes') : 'No' }} {!! ($count = (int) ($childAccount->pending_topic_count ?? 0)) > 0 ? ' - <a href="'.route('account.children.approvals').'#child-'.$childAccount->id.'" class="text-sky-700 hover:text-sky-900">'.$count.' Pending</a>' : '' !!}</div>
                                    <div><span class="font-semibold">Can reply to posts</span>: {{ $childAccount->child_can_reply_in_forum ? ($childAccount->child_forum_reply_requires_approval ? 'Approval required' : 'Yes') : 'No' }} {!! ($count = (int) ($childAccount->pending_reply_count ?? 0)) > 0 ? ' - <a href="'.route('account.children.approvals').'#child-'.$childAccount->id.'" class="text-sky-700 hover:text-sky-900">'.$count.' Pending</a>' : '' !!}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="space-y-6">
                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Account access</div>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900">What this page controls</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Child accounts can sign in separately, but you keep control over approval queues and discussion permissions.
                    </p>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Approvals</div>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900">Review queued posts</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Open the approvals queue to review pending threads and replies from your child accounts.
                    </p>
                    <div class="mt-4">
                        <x-ui.button href="{{ route('account.children.approvals') }}" color="primary-outline">Open approvals queue</x-ui.button>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Back</div>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Return to your account settings to manage profile details, devices, and security settings.
                    </p>
                    <div class="mt-4">
                        <x-ui.button href="{{ route('account.show') }}" color="primary-outline">Back to account settings</x-ui.button>
                    </div>
                </div>
            </section>
        </div>
    </x-container>
</x-layout>
