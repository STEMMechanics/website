@php
    $isNew = (bool) ($isNew ?? false);
    $pendingTopics = collect($pendingTopics ?? []);
    $pendingReplies = collect($pendingReplies ?? []);
@endphp

<x-layout>
    <x-mast backRoute="account.show" backTitle="Account Settings">
        {{ $isNew ? 'Create Child Account' : 'Manage Child Account' }}
    </x-mast>

    <x-container inner-class="max-w-5xl">
        <div class="my-8 space-y-6">
            <form
                method="POST"
                action="{{ $isNew ? route('account.children.store') : route('account.children.update', $child) }}"
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
            >
                @csrf
                @unless($isNew)
                    @method('PUT')
                @endunless

                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ $isNew ? 'Child account details' : $child->username }}</h2>
                        <p class="mt-1 text-sm text-gray-600">Child accounts sign in with username and password and can only use the discussion forums.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <x-ui.input label="Username" name="username" value="{{ old('username', $child->username) }}" info="Unique across all accounts." />
                    <div class="rounded-2xl bg-gray-50 px-4 py-4 text-sm text-gray-600">
                        <div class="font-semibold text-gray-900">Forum access</div>
                        <div class="mt-2">Use the options below to decide whether this child can create threads, reply, and whether either action needs parent approval first.</div>
                    </div>
                    <x-ui.input type="password" label="{{ $isNew ? 'Password' : 'New password (optional)' }}" name="password" value="" />
                    <x-ui.input type="password" label="{{ $isNew ? 'Confirm password' : 'Confirm new password' }}" name="password_confirmation" value="" />
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Thread creation</h3>
                        <div class="mt-4 space-y-3">
                            <input type="hidden" name="child_can_create_forum_topics" value="0" />
                            <x-ui.checkbox
                                label="Allow creating threads"
                                name="child_can_create_forum_topics"
                                value="1"
                                checked="{{ old('child_can_create_forum_topics', $child->child_can_create_forum_topics ?? true) }}"
                            />
                            <input type="hidden" name="child_forum_topic_requires_approval" value="0" />
                            <x-ui.checkbox
                                label="Require approval before a thread goes live"
                                name="child_forum_topic_requires_approval"
                                value="1"
                                checked="{{ old('child_forum_topic_requires_approval', $child->child_forum_topic_requires_approval ?? false) }}"
                            />
                            <input type="hidden" name="child_parent_notified_on_forum_topics" value="0" />
                            <x-ui.checkbox
                                label="Email me when this child creates a thread"
                                name="child_parent_notified_on_forum_topics"
                                value="1"
                                checked="{{ old('child_parent_notified_on_forum_topics', $child->child_parent_notified_on_forum_topics ?? false) }}"
                            />
                        </div>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Replies</h3>
                        <div class="mt-4 space-y-3">
                            <input type="hidden" name="child_can_reply_in_forum" value="0" />
                            <x-ui.checkbox
                                label="Allow replying to posts"
                                name="child_can_reply_in_forum"
                                value="1"
                                checked="{{ old('child_can_reply_in_forum', $child->child_can_reply_in_forum ?? true) }}"
                            />
                            <input type="hidden" name="child_forum_reply_requires_approval" value="0" />
                            <x-ui.checkbox
                                label="Require approval before a reply goes live"
                                name="child_forum_reply_requires_approval"
                                value="1"
                                checked="{{ old('child_forum_reply_requires_approval', $child->child_forum_reply_requires_approval ?? false) }}"
                            />
                            <input type="hidden" name="child_parent_notified_on_forum_replies" value="0" />
                            <x-ui.checkbox
                                label="Email me when this child replies"
                                name="child_parent_notified_on_forum_replies"
                                value="1"
                                checked="{{ old('child_parent_notified_on_forum_replies', $child->child_parent_notified_on_forum_replies ?? false) }}"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <x-ui.button type="submit">{{ $isNew ? 'Create child account' : 'Save child account' }}</x-ui.button>
                </div>
            </form>

            @unless($isNew)
                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Pending Discussion Approval</h2>
                            <p class="mt-1 text-sm text-gray-600">Approve or discard pending threads and replies from this child account.</p>
                        </div>
                    </div>

                    @if($pendingTopics->isEmpty() && $pendingReplies->isEmpty())
                        <div class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500">
                            There is nothing waiting for approval right now.
                        </div>
                    @else
                        <div class="mt-5 space-y-4">
                            @foreach($pendingTopics as $entry)
                                @php($topic = $entry['topic'])
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pending thread</div>
                                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $topic->plainTitle() }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $topic->category?->name }} · {{ $topic->created_at?->format('j M Y g:i a') }}</div>
                                            <div class="mt-3 text-sm text-gray-600">{{ $entry['preview'] !== '' ? $entry['preview'] : 'No preview available.' }}</div>
                                        </div>
                                        <div class="flex gap-3">
                                            <form method="POST" action="{{ route('account.children.topic.approve', ['child' => $child, 'forumTopic' => $topic]) }}">
                                                @csrf
                                                <x-ui.button type="submit" color="primary">Approve</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('account.children.topic.reject', ['child' => $child, 'forumTopic' => $topic]) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Discard thread?', 'This will permanently discard the pending thread.', $el, 'Discard')">
                                                @csrf
                                                <x-ui.button type="submit" color="danger-outline">Discard</x-ui.button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @foreach($pendingReplies as $entry)
                                @php($post = $entry['post'])
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pending reply</div>
                                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $post->topic?->plainTitle() ?: 'Discussion thread' }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $post->topic?->category?->name }} · {{ $post->created_at?->format('j M Y g:i a') }}</div>
                                            <div class="mt-3 text-sm text-gray-600">{{ $entry['preview'] !== '' ? $entry['preview'] : 'No preview available.' }}</div>
                                        </div>
                                        <div class="flex gap-3">
                                            <form method="POST" action="{{ route('account.children.post.approve', ['child' => $child, 'forumPost' => $post]) }}">
                                                @csrf
                                                <x-ui.button type="submit" color="primary">Approve</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('account.children.post.reject', ['child' => $child, 'forumPost' => $post]) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Discard reply?', 'This will permanently discard the pending reply.', $el, 'Discard')">
                                                @csrf
                                                <x-ui.button type="submit" color="danger-outline">Discard</x-ui.button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="rounded-3xl border border-red-200 bg-red-50/60 p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Delete Child Account</h2>
                            <p class="mt-1 text-sm text-gray-600">This anonymizes the child account, releases the username, and disables sign-in.</p>
                        </div>
                        <form method="POST" action="{{ route('account.children.destroy', $child) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete child account?', 'This will anonymize the child account and cannot be undone.', $el)" class="flex flex-col items-start gap-3 sm:items-end">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="delete_discussion_threads" value="0" />
                            <x-ui.checkbox label="Also delete discussion threads this child created and replace their replies with deleted placeholders" name="delete_discussion_threads" value="1" small="true" class="mb-0 max-w-sm text-left" />
                            <x-ui.button type="submit" color="danger">Delete child account</x-ui.button>
                        </form>
                    </div>
                </section>
            @endunless
        </div>
    </x-container>
</x-layout>
