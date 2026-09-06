<x-mail::message>
# Site error

Reference: {{ $context['errorId'] ?? 'unknown' }}

Type: {{ $exceptionClass }}

Route: {{ $context['requestRoute'] ?? 'console' }}

Method: {{ $context['requestMethod'] ?? 'console' }}

Use the reference to locate the event in restricted application logs.
</x-mail::message>
