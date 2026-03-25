@if(\App\Support\AltchaTrust::shouldRequire(request()))
@php($altchaError = $errors->first('altcha'))
<div class="my-2">
    <altcha-widget
        challengeurl="{{ route('altcha-challenge') }}"
        strings='{"waitAlert":""}'
        style="{{ $altchaError !== '' ? 'display:block' : 'display:none' }}"
        data-altcha-hidden-widget="1"></altcha-widget>
    <input type="hidden" name="altcha" value="" data-altcha-field />
    <p
        class="mt-2 text-sm text-red-600 {{ $altchaError !== '' ? '' : 'hidden' }}"
        data-altcha-error
    >{{ $altchaError !== '' ? $altchaError : 'Verification failed. Please retry the challenge.' }}</p>
</div>
@endif

@pushOnce('scripts')
<script type="module" src="{{ asset('vendor/altcha/altcha.min.js') }}"></script>
<script>
    const initAltchaForms = () => {
        const setFormProcessing = (form, isProcessing) => {
            if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                window.SM.setFormProcessing(form, isProcessing, { submitLabel: 'Verifying...' });
            }
        };

        const clearAltchaWatchdog = (form) => {
            const timerId = Number(form.dataset.altchaWatchdogTimer || '0');
            if (timerId > 0) {
                window.clearTimeout(timerId);
            }
            delete form.dataset.altchaWatchdogTimer;
        };

        const clearAltchaSubmitState = (form) => {
            delete form.dataset.altchaSubmitPending;
            delete form.dataset.altchaManualSubmitRequired;
        };

        const clearSingleSubmitLock = (form) => {
            delete form.dataset.smSingleSubmitLocked;
            delete form.dataset.smSingleSubmitAllowNext;
        };

        const armAltchaWatchdog = (form, widget) => {
            clearAltchaWatchdog(form);

            const timeoutMs = 10000;
            const timerId = window.setTimeout(() => {
                // Failsafe: if ALTCHA verification hangs, unlock the form
                // and reveal the widget so users can retry visibly.
                setFormProcessing(form, false);
                clearSingleSubmitLock(form);
                resetAltchaWidget(widget);
                revealAltchaWidget(widget);
                showAltchaError(form, 'Verification timed out. Please try again.');
            }, timeoutMs);

            form.dataset.altchaWatchdogTimer = String(timerId);
        };

        const showAltchaError = (form, message) => {
            const errorElement = form.querySelector('[data-altcha-error]');
            if (!errorElement) {
                return;
            }

            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        };

        const hideAltchaError = (form) => {
            const errorElement = form.querySelector('[data-altcha-error]');
            if (!errorElement) {
                return;
            }

            errorElement.classList.add('hidden');
        };

        const revealAltchaWidget = (widget) => {
            widget.style.display = 'block';
            if (typeof widget.show === 'function') {
                try {
                    widget.show();
                } catch (e) {}
            }
        };

        const resetAltchaWidget = (widget) => {
            if (typeof widget.reset !== 'function') {
                return;
            }

            try {
                widget.reset();
            } catch (e) {}
        };

        document.querySelectorAll('altcha-widget').forEach((widget) => {
            if (widget.dataset.altchaBound === '1') {
                return;
            }
            widget.dataset.altchaBound = '1';

            const form = widget.closest('form');
            if (!form) {
                return;
            }
            form.setAttribute('novalidate', 'novalidate');

            const input = form.querySelector('input[data-altcha-field]');
            if (!input) {
                return;
            }

            if (window.SM && typeof window.SM.bindFormProcessingOnSubmit === 'function') {
                window.SM.bindFormProcessingOnSubmit(form, { submitLabel: 'Verifying...' });
            }

            if (form.dataset.altchaSubmitWatchBound !== '1') {
                form.dataset.altchaSubmitWatchBound = '1';
                form.addEventListener('submit', (event) => {
                    if (String(input.value || '').trim() !== '') {
                        clearAltchaWatchdog(form);
                        clearAltchaSubmitState(form);

                        return;
                    }

                    event.preventDefault();
                    form.dataset.altchaSubmitPending = '1';
                    form.dataset.altchaManualSubmitRequired = '0';
                    hideAltchaError(form);
                    setFormProcessing(form, true);
                    armAltchaWatchdog(form, widget);

                    if (typeof widget.verify === 'function') {
                        try {
                            widget.verify();
                        } catch (e) {
                            clearAltchaWatchdog(form);
                            clearAltchaSubmitState(form);
                            setFormProcessing(form, false);
                            showAltchaError(form, 'Verification could not start. Please try again.');
                        }

                        return;
                    }

                    clearAltchaWatchdog(form);
                    clearAltchaSubmitState(form);
                    setFormProcessing(form, false);
                    showAltchaError(form, 'Verification is still loading. Please wait a moment and try again.');
                });
            }

            widget.addEventListener('statechange', (event) => {
                const detail = event.detail || {};

                if(detail.state === 'verifying') {
                    setFormProcessing(form, true);
                    hideAltchaError(form);
                    armAltchaWatchdog(form, widget);
                }

                if (detail.state === 'verified' && detail.payload) {
                    clearAltchaWatchdog(form);
                    input.value = String(detail.payload);

                    // Keep the widget invisible unless a visible fallback was required.
                    if (widget.dataset.altchaHiddenWidget === '1') {
                        widget.style.display = 'none';
                    }
                    hideAltchaError(form);

                    const shouldResumeSubmit = form.dataset.altchaSubmitPending === '1'
                        && form.dataset.altchaManualSubmitRequired !== '1';
                    clearAltchaSubmitState(form);

                    if (!shouldResumeSubmit) {
                        clearSingleSubmitLock(form);
                        setFormProcessing(form, false);

                        return;
                    }

                    window.requestAnimationFrame(() => {
                        form.dataset.smSingleSubmitAllowNext = '1';
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();

                            return;
                        }

                        form.submit();
                    });

                    return;
                }

                // If ALTCHA requests a visible challenge, reveal the widget.
                if (detail.state === 'code' || detail.state === 'unverified') {
                    clearAltchaWatchdog(form);
                    form.dataset.altchaManualSubmitRequired = '1';
                    delete form.dataset.altchaSubmitPending;
                    clearSingleSubmitLock(form);
                    setFormProcessing(form, false);
                    revealAltchaWidget(widget);
                    hideAltchaError(form);
                    return;
                }

                if (detail.state === 'error' || detail.state === 'expired' || detail.state === 'failed') {
                    clearAltchaWatchdog(form);
                    clearAltchaSubmitState(form);
                    clearSingleSubmitLock(form);
                    setFormProcessing(form, false);
                    input.value = '';
                    resetAltchaWidget(widget);
                    revealAltchaWidget(widget);
                    hideAltchaError(form);
                    return;
                }

                input.value = '';
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAltchaForms, {
            once: true
        });
    } else {
        initAltchaForms();
    }
</script>
@endPushOnce
