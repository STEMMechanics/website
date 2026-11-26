const organisationSwitcher = document.querySelector('[data-account-organisation-switcher]');
const organisationDetailsForm = document.querySelector('[data-account-organisation-details-form]');

if (organisationSwitcher && organisationDetailsForm) {
    const organisationSelect = organisationSwitcher.elements.namedItem('organisation_id');
    const editableFields = [...organisationDetailsForm.elements].filter((field) => {
        if (!field.name || field.disabled) return false;
        return !(field instanceof HTMLInputElement && ['hidden', 'submit', 'button', 'reset'].includes(field.type));
    });
    const fieldValue = (field) => field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)
        ? field.checked
        : field.value;
    const originalValues = new Map(editableFields.map((field) => [field, fieldValue(field)]));
    const hasUnsavedChanges = () => editableFields.some((field) => fieldValue(field) !== originalValues.get(field));
    let currentOrganisationId = organisationSelect?.value || '';
    let navigationAllowed = false;

    organisationDetailsForm.addEventListener('submit', () => { navigationAllowed = true; });
    organisationSwitcher.addEventListener('submit', () => { navigationAllowed = true; });
    window.addEventListener('beforeunload', (event) => {
        if (navigationAllowed || !hasUnsavedChanges()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    organisationSelect?.addEventListener('change', async () => {
        const nextOrganisationId = organisationSelect.value;
        if (!nextOrganisationId || nextOrganisationId === currentOrganisationId) return;

        if (hasUnsavedChanges()) {
            if (typeof window.SM?.confirm !== 'function') {
                organisationSelect.value = currentOrganisationId;
                return;
            }

            const confirmed = (await window.SM.confirm(
                'Unsaved organisation changes',
                'Switching organisations will discard the changes you have not saved.',
                'Switch organisation',
            )).isConfirmed;

            if (!confirmed) {
                organisationSelect.value = currentOrganisationId;
                return;
            }
        }

        currentOrganisationId = nextOrganisationId;
        navigationAllowed = true;
        organisationSwitcher.requestSubmit();
    });
}

const accountForm = document.querySelector('[data-account-settings-form]');
let refreshOrganisationAddressOptions = () => {};

if (accountForm) {
    let activePrimaryOrganisationId = accountForm.querySelector('[name="primary_organisation_id"]:checked')?.value || '';
    const useBillingOrganisation = accountForm.querySelector('[name="use_organisation_billing_address"]');
    const useShippingOrganisation = accountForm.querySelector('[name="use_organisation_shipping_address"]');
    const billingOrganisationOption = accountForm.querySelector('[data-account-organisation-address-option="billing"]');
    const shippingOrganisationOption = accountForm.querySelector('[data-account-organisation-address-option="shipping"]');
    const billingOrganisationPreview = accountForm.querySelector('[data-account-organisation-address-preview="billing"]');
    const shippingOrganisationPreview = accountForm.querySelector('[data-account-organisation-address-preview="shipping"]');
    const shippingSameBilling = accountForm.querySelector('[name="shipping_same_billing"]');
    const billingFields = accountForm.querySelector('[data-account-address-fields="billing"]');
    const shippingFields = accountForm.querySelector('[data-account-address-fields="shipping"]');

    const updateAddressModes = (source = null) => {
        if (source === useBillingOrganisation && useBillingOrganisation?.checked && shippingSameBilling?.checked) {
            if (useShippingOrganisation && !useShippingOrganisation.disabled) {
                useShippingOrganisation.checked = true;
                shippingSameBilling.checked = false;
            }
        }
        if (source === useShippingOrganisation && useShippingOrganisation?.checked && shippingSameBilling) {
            shippingSameBilling.checked = false;
        }
        if (source === shippingSameBilling && shippingSameBilling?.checked) {
            if (useBillingOrganisation?.checked && useShippingOrganisation && !useShippingOrganisation.disabled) {
                useShippingOrganisation.checked = true;
                shippingSameBilling.checked = false;
            }
        }

        if (billingFields) billingFields.disabled = Boolean(useBillingOrganisation?.checked);
        if (shippingFields) shippingFields.disabled = Boolean(useShippingOrganisation?.checked);
        if (shippingSameBilling) shippingSameBilling.disabled = Boolean(useShippingOrganisation?.checked);

        if (shippingSameBilling?.checked) window.SM.updateShippingAddress();
        else shippingFields?.querySelectorAll('input[readonly]').forEach((input) => input.removeAttribute('readonly'));

        if (billingOrganisationPreview) billingOrganisationPreview.hidden = !useBillingOrganisation?.checked;
        if (shippingOrganisationPreview) shippingOrganisationPreview.hidden = !useShippingOrganisation?.checked;
    };

    const updateSelectedOrganisation = (clearAddressChoices = false) => {
        const selectedOrganisation = accountForm.querySelector('[name="primary_organisation_id"]:checked');
        const selectedOrganisationId = selectedOrganisation?.value || '';
        const selectedOrganisationName = selectedOrganisation?.dataset.organisationName || 'organisation';

        if (clearAddressChoices && selectedOrganisationId !== activePrimaryOrganisationId) {
            if (useBillingOrganisation) useBillingOrganisation.checked = false;
            if (useShippingOrganisation) useShippingOrganisation.checked = false;
        }
        activePrimaryOrganisationId = selectedOrganisationId;

        const updateOption = (type, checkbox, wrapper, preview) => {
            if (!checkbox || !wrapper) return;

            const summary = selectedOrganisation?.dataset[`organisation${type}Address`] || '';
            const hasPrimaryOrganisation = selectedOrganisationId !== '';
            wrapper.hidden = !hasPrimaryOrganisation;
            checkbox.disabled = !hasPrimaryOrganisation;
            if (!hasPrimaryOrganisation) checkbox.checked = false;

            const labelText = checkbox.closest('label')?.querySelector('div > div');
            if (labelText) labelText.textContent = `Use ${selectedOrganisationName} ${type.toLowerCase()} address`;
            if (preview) {
                preview.textContent = summary || `No ${type.toLowerCase()} address has been saved for this organisation yet.`;
            }
        };

        updateOption('Billing', useBillingOrganisation, billingOrganisationOption, billingOrganisationPreview);
        updateOption('Shipping', useShippingOrganisation, shippingOrganisationOption, shippingOrganisationPreview);
        updateAddressModes();
    };
    refreshOrganisationAddressOptions = () => updateSelectedOrganisation();

    [useBillingOrganisation, useShippingOrganisation, shippingSameBilling].filter(Boolean).forEach((checkbox) => {
        checkbox.addEventListener('change', () => updateAddressModes(checkbox));
    });
    accountForm.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[name="primary_organisation_id"]')) {
            updateSelectedOrganisation(true);
        }
    });
    updateSelectedOrganisation();
}

const organisationFormForLinking = document.getElementById('account-settings-form');
const organisationLinkDialog = document.getElementById('account-organisation-link-dialog');
const organisationInput = organisationLinkDialog?.querySelector('[name="organisation_name"]');
const keepOrganisationInput = organisationLinkDialog?.querySelector('[name="organisation_keep_as_entered"]');
const organisationDetailsDialog = document.getElementById('account-organisation-details-dialog');
const organisationDetailsName = organisationDetailsDialog?.querySelector('[data-new-organisation-name]');
const organisationTypeInput = organisationDetailsDialog?.querySelector('[name="new_organisation_type"]');
const organisationTypeError = organisationDetailsDialog?.querySelector('[data-organisation-type-error]');
const organisationMatchDialog = document.getElementById('account-organisation-match-dialog');
const organisationMatchList = organisationMatchDialog?.querySelector('[data-organisation-match-candidates]');
const organisationList = document.querySelector('[data-account-organisation-list]');
const organisationActionForms = document.querySelector('[data-account-organisation-action-forms]');
const organisationDetailFields = [...(organisationFormForLinking?.querySelectorAll('[name^="new_organisation_"]') || [])];
const normaliseOrganisationName = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase();
let detailsOrganisationName = '';
const clearOrganisationDetails = () => {
    organisationDetailFields.forEach((field) => { field.value = ''; });
    detailsOrganisationName = '';
};
const prepareOrganisationDetails = () => {
    const currentName = normaliseOrganisationName(organisationInput?.value);
    if (currentName !== detailsOrganisationName) clearOrganisationDetails();
    detailsOrganisationName = currentName;
};
let existingOrganisationNames = [];
try {
    existingOrganisationNames = JSON.parse(organisationInput?.dataset.organisationNames || '[]');
} catch {
    existingOrganisationNames = [];
}

const isExistingOrganisationNameByValue = (value) => {
    const name = normaliseOrganisationName(value);
    return name !== '' && existingOrganisationNames.some((existingName) => normaliseOrganisationName(existingName) === name);
};
const isExistingOrganisationName = () => isExistingOrganisationNameByValue(organisationInput?.value);

const setOrganisationStatus = (dialog, message, tone = 'info') => {
    const status = dialog?.querySelector('[data-organisation-link-status]');
    if (!status) return;
    status.textContent = message;
    status.className = `mt-3 rounded-lg border px-3 py-2 text-sm ${tone === 'danger' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-sky-200 bg-sky-50 text-sky-900'}`;
    status.hidden = !message;
};

const setOrganisationTypeError = (message = '') => {
    if (!organisationTypeError || !(organisationTypeInput instanceof HTMLSelectElement)) return;
    organisationTypeError.textContent = message;
    organisationTypeError.hidden = !message;
    organisationTypeError.classList.toggle('hidden', !message);
    organisationTypeError.classList.toggle('border-rose-200', Boolean(message));
    organisationTypeError.classList.toggle('bg-rose-50', Boolean(message));
    organisationTypeError.classList.toggle('text-rose-800', Boolean(message));
    organisationTypeInput.setAttribute('aria-invalid', message ? 'true' : 'false');
};

const setOrganisationInputValue = (value) => {
    if (!(organisationInput instanceof HTMLInputElement)) return;
    const nextValue = String(value || '');
    organisationInput.value = nextValue;

    const suggestionRoot = organisationInput.closest('[x-data]');
    if (suggestionRoot && window.Alpine) {
        try {
            const autocomplete = window.Alpine.$data(suggestionRoot);
            autocomplete.rawValue = nextValue;
            autocomplete.hasTyped = false;
            autocomplete.suppressRefresh = false;
            autocomplete.filtered = [];
            autocomplete.selectedIndex = -1;
            autocomplete.open = false;
        } catch (_) {}
    }
    syncOrganisationDetailsName();
};

const syncOrganisationDetailsName = () => {
    if (organisationDetailsName) {
        organisationDetailsName.textContent = organisationInput?.value.trim() || 'this organisation';
    }
};

const closeOrganisationSuggestions = () => {
    const suggestionRoot = organisationInput?.closest('[x-data]');
    if (suggestionRoot && window.Alpine) {
        try { window.Alpine.$data(suggestionRoot).open = false; } catch (_) {}
    }
};

const showOrganisationDetails = () => {
    closeOrganisationSuggestions();
    syncOrganisationDetailsName();
    setOrganisationStatus(organisationDetailsDialog, '');
    if (!organisationDetailsDialog?.open) organisationDetailsDialog?.showModal();
    organisationTypeInput?.focus();
};

const renderOrganisationMatchCandidates = (html) => {
    if (!organisationMatchList || !organisationMatchDialog) return;
    closeOrganisationSuggestions();
    organisationMatchList.innerHTML = html;
    if (organisationDetailsDialog?.open) organisationDetailsDialog.close();
    setOrganisationStatus(organisationLinkDialog, '');
    if (!organisationMatchDialog.open) organisationMatchDialog.showModal();
};

const setOrganisationLinkButtonsBusy = (busy) => {
    organisationFormForLinking?.querySelectorAll('[data-organisation-link-continue], [data-organisation-details-submit], [data-organisation-match-keep]').forEach((button) => {
        button.disabled = busy;
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    });
};

const submitOrganisationLink = async () => {
    if (!(organisationInput instanceof HTMLInputElement) || !keepOrganisationInput || !organisationFormForLinking) return;
    const name = organisationInput.value.trim();
    if (!name) {
        setOrganisationStatus(organisationLinkDialog, 'Enter or select an organisation first.', 'danger');
        organisationInput.focus();
        return;
    }

    const isNew = !isExistingOrganisationName();
    if (isNew && (!organisationTypeInput || !organisationTypeInput.value)) {
        showOrganisationDetails();
        setOrganisationTypeError('Choose an organisation type to continue.');
        organisationTypeInput?.focus();
        return;
    }

    const payload = new FormData();
    payload.append('_token', organisationFormForLinking.querySelector('[name="_token"]')?.value
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '');
    payload.append('organisation_name', name);
    payload.append('organisation_keep_as_entered', keepOrganisationInput.value || '0');
    organisationDetailFields.forEach((field) => payload.append(field.name, field.value));

    setOrganisationStatus(organisationDetailsDialog?.open ? organisationDetailsDialog : organisationLinkDialog, '');
    setOrganisationTypeError('');
    setOrganisationLinkButtonsBusy(true);

    try {
        const response = await fetch(organisationFormForLinking.dataset.organisationLinkEndpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload,
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));

        if (response.status === 422) {
            const errors = data.errors || {};
            const typeMessage = errors.new_organisation_type?.[0];
            if (typeMessage) {
                showOrganisationDetails();
                setOrganisationTypeError(typeMessage);
                organisationTypeInput?.focus();
            } else {
                const firstError = Object.values(errors).flat()[0] || data.message || 'Check the organisation details and try again.';
                setOrganisationStatus(organisationDetailsDialog?.open ? organisationDetailsDialog : organisationLinkDialog, firstError, 'danger');
            }
            return;
        }
        if (!response.ok) throw new Error(data.message || 'Could not add this organisation.');

        if (data.state === 'match_candidates') {
            renderOrganisationMatchCandidates(data.organisation_match_candidates_html || '');
            return;
        }

        if (data.organisation_name && !isExistingOrganisationNameByValue(data.organisation_name)) {
            existingOrganisationNames.push(data.organisation_name);
        }

        if (data.organisation_list_html && organisationList) {
            organisationList.innerHTML = data.organisation_list_html;
            if (organisationActionForms) {
                organisationActionForms.innerHTML = data.organisation_action_forms_html || '';
                window.Alpine?.initTree(organisationActionForms);
            }
            refreshOrganisationAddressOptions();
        }

        const noticeTone = ['already_linked', 'already_pending'].includes(data.state) ? 'info' : 'success';
        const noticeTitle = {
            already_linked: 'Already linked',
            already_pending: 'Request already sent',
            pending: 'Request sent',
            auto_approved: 'Organisation linked',
            created: 'Organisation created',
        }[data.state] || 'Organisation request updated';
        organisationMatchDialog?.close();
        organisationDetailsDialog?.close();
        organisationLinkDialog?.close();
        closeOrganisationSuggestions();
        window.SM?.banner(noticeTitle, data.message || 'Your organisation list is up to date.', noticeTone);
    } catch (error) {
        setOrganisationStatus(organisationDetailsDialog?.open ? organisationDetailsDialog : organisationLinkDialog, error.message || 'Could not add this organisation.', 'danger');
    } finally {
        setOrganisationLinkButtonsBusy(false);
    }
};

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const continueAdding = target?.closest('[data-organisation-link-continue]');
    if (!continueAdding || !(organisationInput instanceof HTMLInputElement)) return;

    event.preventDefault();
    event.stopPropagation();
    if (normaliseOrganisationName(organisationInput.value) && !isExistingOrganisationName()) {
        prepareOrganisationDetails();
        keepOrganisationInput.value = '0';
        setOrganisationTypeError('');
        showOrganisationDetails();
        return;
    }
    submitOrganisationLink();
}, true);

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const submitDetails = target?.closest('[data-organisation-details-submit]');
    if (!submitDetails) return;
    event.preventDefault();
    event.stopPropagation();
    submitOrganisationLink();
}, true);

organisationFormForLinking?.addEventListener('submit', (event) => {
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    if (!submitter?.matches('[data-organisation-link-continue], [data-organisation-details-submit]')
        && !organisationLinkDialog?.open && !organisationDetailsDialog?.open) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    if (organisationDetailsDialog?.open || submitter?.matches('[data-organisation-details-submit]')) submitOrganisationLink();
    else if (organisationInput instanceof HTMLInputElement && !isExistingOrganisationName()) {
        prepareOrganisationDetails();
        if (keepOrganisationInput) keepOrganisationInput.value = '0';
        setOrganisationTypeError('');
        showOrganisationDetails();
    }
    else submitOrganisationLink();
}, true);

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const backToOrganisationName = target?.closest('[data-organisation-details-back]');
    if (!backToOrganisationName || !organisationDetailsDialog) return;
    event.preventDefault();
    organisationDetailsDialog.close();
    if (keepOrganisationInput) keepOrganisationInput.value = '0';
    organisationInput?.focus();
});

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const useOrganisation = target?.closest('[data-organisation-match-use]');
    const keepEntry = target?.closest('[data-organisation-match-keep]');
    const cancelMatch = target?.closest('[data-organisation-match-cancel]');
    if (!useOrganisation && !keepEntry && !cancelMatch) return;
    event.preventDefault();

    if (cancelMatch) {
        setOrganisationInputValue('');
        keepOrganisationInput.value = '0';
        clearOrganisationDetails();
        organisationMatchDialog?.close();
        setOrganisationStatus(organisationLinkDialog, '');
        return;
    }

    if (useOrganisation) {
        setOrganisationInputValue(useOrganisation.dataset.organisationName || '');
        keepOrganisationInput.value = '0';
        clearOrganisationDetails();
    } else {
        keepOrganisationInput.value = '1';
        setOrganisationTypeError('');
        organisationMatchDialog?.close();
        showOrganisationDetails();
        return;
    }
    organisationMatchDialog?.close();
    submitOrganisationLink();
});

if (organisationInput instanceof HTMLInputElement && keepOrganisationInput instanceof HTMLInputElement) {
    organisationInput.addEventListener('input', () => {
        keepOrganisationInput.value = '0';
        setOrganisationStatus(organisationLinkDialog, '');
        syncOrganisationDetailsName();
    });
}
organisationTypeInput?.addEventListener('change', () => setOrganisationTypeError(''));
organisationLinkDialog?.addEventListener('close', () => {
    setOrganisationInputValue('');
    if (keepOrganisationInput instanceof HTMLInputElement) keepOrganisationInput.value = '0';
    clearOrganisationDetails();
    setOrganisationStatus(organisationLinkDialog, '');
    setOrganisationStatus(organisationDetailsDialog, '');
    setOrganisationTypeError('');
});
