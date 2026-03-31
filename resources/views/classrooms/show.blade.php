@php
    $classSession = $state['classSession'] ?? [];
    $workshop = $state['workshop'] ?? [];
    $viewer = $state['viewer'] ?? [];
    $pageTitle = trim((string) ($classSession['title'] ?? 'Course'));
    $tabs = [];
    if (! empty($classSession['slug'] ?? '')) {
        $tabs[] = [
            'title' => 'Course',
            'route' => route('class.show', $classSession['slug']),
        ];
    }
    if (! empty($classSession['forumCategoryUrl'] ?? '')) {
        $tabs[] = [
            'title' => 'Forum',
            'route' => (string) $classSession['forumCategoryUrl'],
            'badge' => (int) ($forumUnreadCount ?? 0),
        ];
    }
@endphp

<x-layout :title="$pageTitle" :description="trim((string) ($classSession['summary'] ?? ''))">
    @push('head')
        @vite('resources/js/classroom.jsx')
    @endpush

    <x-mast :back-title="__('Back')" :back-route="'account.course.index'" :description="trim((string) ($classSession['summary'] ?? ''))" :tabs="$tabs">{{ $pageTitle }}</x-mast>

    <x-container inner-class="max-w-7xl" class="py-8">
        <div
            id="classroom-root"
            data-state='@json($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)'
            data-livekit-url="{{ $livekitUrl }}"
            data-token-endpoint="{{ $tokenEndpoint }}"
            data-help-requests-endpoint="{{ $helpRequestStateEndpoint }}"
            data-help-request-store-endpoint="{{ $helpRequestStoreEndpoint }}"
            data-help-request-approve-pattern="{{ $helpRequestApprovePattern }}"
            data-help-request-revoke-pattern="{{ $helpRequestRevokePattern }}"
            data-broadcast-start-endpoint="{{ $broadcastStartEndpoint }}"
            data-broadcast-end-endpoint="{{ $broadcastEndEndpoint }}"
            data-chat-store-endpoint="{{ route('class.chat.store', ['classSession' => $classSession['slug'] ?? '']) }}"
            data-chat-clear-endpoint="{{ route('class.chat.clear', ['classSession' => $classSession['slug'] ?? '']) }}"
            data-chat-delete-message-endpoint="{{ route('class.chat.destroy', ['classSession' => $classSession['slug'] ?? '', 'chatMessage' => '__MESSAGE__']) }}"
            data-chat-participant-endpoint="{{ route('class.chat.participant.update', ['classSession' => $classSession['slug'] ?? '', 'user' => '__USER__']) }}"
            data-client-error-endpoint="{{ $clientErrorEndpoint }}"
            data-csrf-token="{{ csrf_token() }}"
            class="min-h-[40rem]"
        >
            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="animate-pulse space-y-4">
                    <div class="h-8 w-2/5 rounded bg-gray-200"></div>
                    <div class="h-4 w-3/5 rounded bg-gray-200"></div>
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_20rem]">
                        <div class="h-80 rounded-3xl bg-gray-100"></div>
                        <div class="space-y-4">
                            <div class="h-36 rounded-3xl bg-gray-100"></div>
                            <div class="h-36 rounded-3xl bg-gray-100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>
