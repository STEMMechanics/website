@component('mail::message')
# {{ $reminder->subject }}

@if($reminder->remindable instanceof \App\Models\Workshop)
**Workshop:** {{ $reminder->remindable->title }}<br>
**Date / Time:** {{ $reminder->remindable->starts_at?->format('D j M Y, g:ia') ?? 'Not specified' }}<br>
**Location:** {{ $reminder->remindable->getLocationName() ?: 'Not specified' }}

@endif
@if(trim((string) $reminder->message) !== '')
## Task Notes

{{ $reminder->message }}
@endif

@if($reminder->action_url)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 24px;">
<tr>
<td style="padding-right: 10px;"><a href="{{ $reminder->action_url }}" class="button button-primary" target="_blank" rel="noopener">View Task</a></td>
@if($reminder->remindable instanceof \App\Models\Workshop && $reminder->source instanceof \App\Models\WorkshopTemplateTask)
<td><a href="{{ route('admin.workshop.run-sheet.task.complete', [$reminder->remindable, $reminder->source]) }}" class="button" target="_blank" rel="noopener" style="background-color: #16a34a; border: 8px solid #16a34a; border-left-width: 18px; border-right-width: 18px; color: #ffffff;">Mark as Complete</a></td>
@endif
</tr>
</table>
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
