@auth
    @if($canReply && !$topic->is_locked)
        <div class="mt-8 flex justify-end">
            <x-ui.button type="button" data-forum-reply-button data-reply-title="Reply to Thread" data-reply-body="">Reply to Thread</x-ui.button>
        </div>
    @elseif($topic->is_locked)
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500 text-center">This thread is locked and cannot receive new replies.</div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500 text-center">You do not have permission to reply in this category.</div>
    @endif
@else
    <div class="mt-8 flex flex-wrap items-center gap-3">
        <span class="text-sm text-gray-500">Log in to reply to this thread.</span>
        <x-ui.button color="outline" href="{{ route('login') }}">Log In</x-ui.button>
    </div>
@endauth
