@props(['field', 'info' => null, 'infoExpression' => null, 'error' => ''])
@if(is_string($infoExpression) && trim($infoExpression) !== '')
    <div class="ml-2 mt-1 text-xs text-gray-500" {{ $field->feedbackAttributes('help') }} x-text="{{ $infoExpression }}"></div>
@elseif($info !== null && $info !== '')
    <div class="ml-2 mt-1 text-xs text-gray-500" {{ $field->feedbackAttributes('help') }}>{{ $info }}</div>
@endif
<div data-validation-error class="ml-2 mt-2 text-xs text-red-600" {{ $field->feedbackAttributes('error') }} role="alert" @if($error === '') hidden @endif>{{ $error }}</div>
