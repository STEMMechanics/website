@props(['form'])

@php($guard = app(\App\Support\FormGuard::class))
@php($formName = trim((string) $form))
@php($honeypotField = $guard->honeypotField($formName))
@php($guardError = $errors->first(\App\Support\FormGuard::ERROR_KEY))

<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
    <label for="{{ $honeypotField }}">Leave this field blank</label>
    <input
        type="text"
        id="{{ $honeypotField }}"
        name="{{ $honeypotField }}"
        value=""
        tabindex="-1"
        autocomplete="off"
    />
</div>

<input type="hidden" name="{{ \App\Support\FormGuard::TOKEN_FIELD }}" value="{{ $guard->issueToken($formName) }}" />

@if($guardError !== '')
    <p class="text-sm text-red-600">{{ $guardError }}</p>
@endif
