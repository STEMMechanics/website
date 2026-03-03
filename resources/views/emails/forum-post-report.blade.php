@component('mail::message')
# Discussion Post Report

**Reported by:** {{ $reporter->getName() ?: ($reporter->username ?: 'Unknown user') }}  
**Reporter email:** {{ $reporter->email ?: '-' }}  
**Post ID:** {{ $post->id }}  
**Author:** {{ $post->user?->getName() ?: ($post->user?->username ?: 'Deleted user') }}  
**Author email:** {{ $post->user?->email ?: '-' }}  
**Topic:** {{ $post->topic?->title ?: '-' }}  
**Link:** [View reported post]({{ $postUrl }})

## Report reason

{{ $reason }}

## Reported post content

{{ \App\Support\ForumContent::plainText((string) $post->body) ?: '[No text content]' }}
@endcomponent
