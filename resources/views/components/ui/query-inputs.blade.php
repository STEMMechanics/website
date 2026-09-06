@props(['values', 'prefix' => null])
@foreach($values as $key => $queryValue)
    @php($inputName = $prefix === null ? $key : $prefix.'['.$key.']')
    @if(is_array($queryValue))
        <x-ui.query-inputs :values="$queryValue" :prefix="$inputName" />
    @elseif(is_scalar($queryValue))
        <input type="hidden" name="{{ $inputName }}" value="{{ $queryValue }}">
    @endif
@endforeach
