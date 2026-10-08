const registerStockAssemblyDialog = () => {
    window.SM = window.SM || {};

    window.SM.stockAssemblyDialog = () => ({
        assemblyDialogLoading: false,
        assemblyDialogError: '',
        assemblyPreviewUrl: '',
        assemblyDialogRequestController: null,
        assemblyPlanUpdateTimer: null,
        assemblyPlanUpdateController: null,

        async openInitialAssembly() {
            const pageUrl = new URL(window.location.href);
            const previewUrl = pageUrl.searchParams.get('assembly_preview');
            if (!previewUrl) return;

            pageUrl.searchParams.delete('assembly_preview');
            window.history.replaceState({}, '', pageUrl);
            await this.openAssemblyDialog(previewUrl);
        },

        async openAssemblyDialog(url) {
            if (!url || this.assemblyDialogLoading) return;

            const previewUrl = new URL(url, window.location.href);
            if (previewUrl.origin !== window.location.origin) return;

            this.assemblyPreviewUrl = previewUrl.toString();
            this.assemblyDialogError = '';
            this.assemblyDialogLoading = true;
            this.assemblyDialogRequestController?.abort();
            const controller = new AbortController();
            this.assemblyDialogRequestController = controller;
            this.$refs.assemblyDialog.showModal();
            try {
                const response = await fetch(previewUrl, {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (this.assemblyDialogRequestController !== controller) return;
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    this.assemblyDialogError = Object.values(payload.errors || {}).flat()[0] || 'Could not load the assembly plan. Check the quantity and try again.';
                    return;
                }

                const html = await response.text();
                if (this.assemblyDialogRequestController !== controller) return;
                this.$refs.assemblyDialogContent.innerHTML = html;
                this.$nextTick(() => this.$refs.assemblyDialog.querySelector('[data-assembly-quantity]')?.focus());
            } catch (error) {
                if (error.name !== 'AbortError' && this.assemblyDialogRequestController === controller) {
                    this.assemblyDialogError = 'Could not load the assembly plan. Try again.';
                }
            } finally {
                if (this.assemblyDialogRequestController === controller) {
                    this.assemblyDialogRequestController = null;
                    this.assemblyDialogLoading = false;
                }
            }
        },

        scheduleAssemblyPlanRefresh(quantity) {
            if (this.assemblyPlanUpdateTimer !== null) window.clearTimeout(this.assemblyPlanUpdateTimer);
            this.assemblyPlanUpdateTimer = null;
            const count = Number(quantity);
            if (!Number.isInteger(count) || count < 1 || count > 100000) {
                this.assemblyPlanUpdateController?.abort();
                return;
            }
            const currentPlan = this.$refs.assemblyDialogContent.querySelector('input[name=quantity]')?.value;
            if (Number(currentPlan) === count) {
                this.assemblyPlanUpdateController?.abort();
                return;
            }
            this.assemblyPlanUpdateTimer = window.setTimeout(() => {
                this.assemblyPlanUpdateTimer = null;
                this.refreshAssemblyPlan(count);
            }, 350);
        },

        async refreshAssemblyPlan(quantity) {
            const currentQuantityField = this.$refs.assemblyDialogContent.querySelector('[data-assembly-quantity]');
            if (!currentQuantityField || Number(currentQuantityField.value) !== quantity) return;
            this.assemblyPlanUpdateController?.abort();
            const controller = new AbortController();
            this.assemblyPlanUpdateController = controller;
            this.assemblyDialogLoading = true;
            this.assemblyDialogError = '';
            const url = new URL(this.assemblyPreviewUrl, window.location.href);
            url.searchParams.set('quantity', String(quantity));

            try {
                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const currentField = this.$refs.assemblyDialogContent.querySelector('[data-assembly-quantity]');
                if (!currentField || Number(currentField.value) !== quantity) return;
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    this.assemblyDialogError = Object.values(payload.errors || {}).flat()[0] || 'Could not update the material quantities.';
                    return;
                }

                const html = await response.text();
                if (Number(this.$refs.assemblyDialogContent.querySelector('[data-assembly-quantity]')?.value) !== quantity) return;
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextTable = parsed.querySelector('[data-assembly-table]');
                const form = this.$refs.assemblyDialogContent.querySelector('form');
                const currentTable = form?.querySelector('[data-assembly-table]');
                if (!nextTable || !currentTable || !form) throw new Error('Assembly plan response was incomplete.');

                const manualUsage = Array.from(form.querySelectorAll('input[name^=actual_usage]'))
                    .filter((input) => input.dataset.userEdited === 'true')
                    .map((input) => [input.name, input.value]);
                currentTable.replaceWith(nextTable);
                const planField = form.elements.namedItem('quantity');
                const completedField = form.elements.namedItem('completed_quantity');
                if (planField) planField.value = String(quantity);
                if (completedField) completedField.max = String(quantity);
                for (const [name, value] of manualUsage) {
                    const input = Array.from(form.querySelectorAll('input[name^=actual_usage]')).find((field) => field.name === name);
                    if (input) {
                        input.value = value;
                        input.dataset.userEdited = 'true';
                    }
                }
            } catch (error) {
                const field = this.$refs.assemblyDialogContent.querySelector('[data-assembly-quantity]');
                if (error.name !== 'AbortError' && this.assemblyPlanUpdateController === controller && Number(field?.value) === quantity) {
                    this.assemblyDialogError = 'Could not update the material quantities. Try again.';
                }
            } finally {
                if (this.assemblyPlanUpdateController === controller) {
                    this.assemblyPlanUpdateController = null;
                    this.assemblyDialogLoading = false;
                }
            }
        },

        handleAssemblyDialogInput(event) {
            this.assemblyDialogError = '';
            if (event.target.matches('input[name^=actual_usage]')) {
                event.target.dataset.userEdited = 'true';
                event.target.closest('td')?.querySelector('[data-assembly-validation-error]')?.remove();
                this.$refs.assemblyDialogContent.querySelector('[data-assembly-validation-error]')?.remove();
            }
            if (event.target.matches('[data-assembly-quantity]')) {
                this.$refs.assemblyDialogContent.querySelectorAll('[data-assembly-validation-error]').forEach((error) => error.remove());
                this.scheduleAssemblyPlanRefresh(event.target.value);
            }
        },

        submitAssemblyDialog(event) {
            const form = event.target;
            const completed = form.elements.namedItem('completed_quantity');
            const planned = form.elements.namedItem('quantity');
            if (this.assemblyDialogLoading) {
                event.preventDefault();
                return;
            }
            if (completed && planned && Number(completed.value) > 0 && Number(completed.value) !== Number(planned.value)) {
                event.preventDefault();
                this.scheduleAssemblyPlanRefresh(completed.value);
            }
        },

        closeAssemblyDialog() {
            if (this.assemblyPlanUpdateTimer !== null) window.clearTimeout(this.assemblyPlanUpdateTimer);
            this.assemblyPlanUpdateTimer = null;
            this.assemblyPlanUpdateController?.abort();
            this.assemblyPlanUpdateController = null;
            this.assemblyDialogRequestController?.abort();
            this.assemblyDialogRequestController = null;
            this.assemblyDialogLoading = false;
            this.assemblyDialogError = '';
            if (this.$refs.assemblyDialog?.open) this.$refs.assemblyDialog.close();
            this.$refs.assemblyDialogContent.innerHTML = '';
        },
    });

    if (window.Alpine?.data) {
        window.Alpine.data('stockAssemblyDialog', window.SM.stockAssemblyDialog);
    }
};

registerStockAssemblyDialog();
document.addEventListener('alpine:init', registerStockAssemblyDialog);
