@component('mail::message')
@php
    $recipientFirstName = trim((string) strtok((string) ($recipientName ?? ''), ' '));
    $resolvedActionLabel = !empty($actionLabel) ? $actionLabel : 'Review Document';
    $bodyMessage = !empty($resolvedFullMessage)
        ? preg_replace('/\{\{\s*(?:pay|action)\s*\}\}/i', '', (string) $resolvedFullMessage)
        : null;
@endphp
@if(!empty($bodyMessage))
{{ $bodyMessage }}

@if((!empty($payUrl) && preg_match('/\{\{\s*pay\s*\}\}/i', (string) $resolvedFullMessage) === 1) || (!empty($actionUrl) && preg_match('/\{\{\s*action\s*\}\}/i', (string) $resolvedFullMessage) === 1))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
    <tr>
        <td align="center">
            @if(!empty($payUrl) && preg_match('/\{\{\s*pay\s*\}\}/i', (string) $resolvedFullMessage) === 1)
                @component('mail::button', ['url' => $payUrl])
                View and Pay Invoice
                @endcomponent
            @endif
            @if(!empty($payUrl) && !empty($actionUrl) && preg_match('/\{\{\s*pay\s*\}\}/i', (string) $resolvedFullMessage) === 1 && preg_match('/\{\{\s*action\s*\}\}/i', (string) $resolvedFullMessage) === 1)
                &nbsp;
            @endif
            @if(!empty($actionUrl) && preg_match('/\{\{\s*action\s*\}\}/i', (string) $resolvedFullMessage) === 1)
                @component('mail::button', ['url' => $actionUrl, 'color' => empty($payUrl) ? 'primary' : 'outline'])
                {{ $resolvedActionLabel }}
                @endcomponent
            @endif
        </td>
    </tr>
</table>
@endif
@else
Hi {{ $recipientFirstName !== '' ? $recipientFirstName : $recipientName }},

Your {{ $documentType }} **{{ $documentNumber }}** is attached as a PDF.

@if(!empty($customMessage))
{{ $customMessage }}

@endif

@if(!empty($payUrl))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
    <tr>
        <td align="center">
            @component('mail::button', ['url' => $payUrl])
            View and Pay Invoice
            @endcomponent
            @if(!empty($actionUrl))
                &nbsp;
                @component('mail::button', ['url' => $actionUrl, 'color' => 'outline'])
                {{ $resolvedActionLabel }}
                @endcomponent
            @endif
        </td>
    </tr>
</table>
@elseif(!empty($actionUrl))
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
    <tr>
        <td align="center">
            @component('mail::button', ['url' => $actionUrl, 'color' => 'primary'])
            {{ $resolvedActionLabel }}
            @endcomponent
        </td>
    </tr>
</table>
@endif
@endif

Thanks,<br>
{{ \App\Support\EmailSignatureFormatter::resolve($initiatedByName ?? null) }}
@endcomponent
