Hi {{ $parentName }},

{{ $childUsername }} has {{ $statusLabel }} a discussion {{ $activityLabel }}

Category: {{ $categoryName }}
Thread: {{ $topicTitle }}

@if($preview !== '')
{{ $preview }}

@endif
@if(!empty($approveUrl))
Approve {{ ucfirst($activityLabel) }}:
{{ $approveUrl }}

@endif
Thanks,
STEMMechanics
