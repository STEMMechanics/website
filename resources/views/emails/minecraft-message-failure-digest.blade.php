@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\MinecraftMessage> $messages */
@endphp

# STEMCraft Blocked Messages

{{ $messages->count() }} Minecraft {{ \Illuminate\Support\Str::plural('message', $messages->count()) }} have been blocked by moderation and are ready for review.

@foreach($messages as $message)
- **{{ $message->username }}** at **{{ $message->occurred_at?->format('j M Y g:i a') ?? '-' }}**
  {{ $message->filtered_message ?: $message->failureSummary() }}
@endforeach

[Review Minecraft messaging]({{ $messagesUrl }})
