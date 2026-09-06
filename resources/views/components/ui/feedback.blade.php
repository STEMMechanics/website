<div {{ $attributes->class(['sm-feedback-inline']) }} data-tone="success" hidden role="status" aria-live="polite" aria-atomic="true">
    <span class="sm-feedback-icon" data-feedback-icon aria-hidden="true"></span>
    <div class="sm-feedback-copy">
        <strong data-feedback-title></strong>
        <p data-feedback-message></p>
    </div>
    <x-ui.button variant="plain" type="button" class="sm-feedback-dismiss" data-feedback-dismiss aria-label="Dismiss notification">×</x-ui.button>
</div>
