@component('mail::message')
Hi {{ $parentName }},

{{ $childUsername }} has {{ $statusLabel }} a discussion {{ $activityLabel }}.

**Category:** {{ $categoryName }}  
**Thread:** {{ $topicTitle }}

@if($preview !== '')
> {{ $preview }}
@endif

@component('mail::button', ['url' => $manageUrl])
Review Child Account
@endcomponent

Thanks,  
STEMMechanics
@endcomponent
