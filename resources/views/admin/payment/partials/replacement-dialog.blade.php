<template x-teleport="body">
    <div
        x-show="replacementDialogOpen"
        x-cloak
        class="fixed inset-0 z-280 flex items-end justify-center bg-slate-950/60 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-replacement-dialog-title"
        @click.self="closeReplacementDialog()"
        @keydown.escape.window="if (replacementDialogOpen) { closeReplacementDialog() }"
    >
        <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-200 px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700" x-text="replacementDialogData?.headline || 'Override EFTPOS payment?'"></div>
                        <h2 id="payment-replacement-dialog-title" class="mt-1 text-xl font-bold text-gray-900">Compare payments</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600" x-text="replacementDialogData?.description || 'Compare the current payment with the matching EFTPOS transaction before replacing it.'"></p>
                    </div>
                    <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closeReplacementDialog()" aria-label="Close replacement dialog">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto px-6 py-6">
                <div x-show="replacementDialogData?.candidates?.length > 1" class="mb-5 w-full">
                    <x-ui.select
                        name="replacement_candidate"
                        label="Select matching payment"
                        :noLabel="false"
                        :disabled="false"
                        class="w-full"
                        innerClass="w-full"
                        selectClass="w-full"
                        x-model="replacementDialogSelectedCandidateId"
                    >
                        <template x-for="candidate in (replacementDialogData?.candidates || [])" :key="candidate.id">
                            <option :value="String(candidate.id)" x-text="candidate.label + ' · ' + candidate.date + ' · ' + candidate.amount"></option>
                        </template>
                    </x-ui.select>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-amber-800">Current payment</h3>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200" x-text="replacementDialogData?.source?.label || '-'"></span>
                        </div>
                        <div class="mt-4 divide-y divide-amber-100 overflow-hidden rounded-xl border border-amber-200 bg-white">
                            <template x-for="row in [
                                ['Date / Time', replacementDialogData?.source?.date],
                                ['Customer', replacementDialogData?.source?.customer],
                                ['Method', replacementDialogData?.source?.method],
                                ['Amount', replacementDialogData?.source?.amount],
                                ['Reference', replacementDialogData?.source?.reference],
                                ['Notes', replacementDialogData?.source?.notes],
                            ]" :key="row[0]">
                                <div class="flex items-start justify-between gap-4 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="row[0]"></dt>
                                    <dd class="text-right text-sm text-gray-900" x-text="row[1] || '-'"></dd>
                                </div>
                            </template>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-sky-200 bg-sky-50/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-sky-800">Selected match</h3>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200" x-text="selectedReplacementCandidate()?.label || '-'"></span>
                        </div>
                        <div class="mt-4 divide-y divide-sky-100 overflow-hidden rounded-xl border border-sky-200 bg-white">
                            <template x-for="row in [
                                ['Date / Time', selectedReplacementCandidate()?.date],
                                ['Customer', selectedReplacementCandidate()?.customer],
                                ['Method', selectedReplacementCandidate()?.method],
                                ['Amount', selectedReplacementCandidate()?.amount],
                                ['Reference', selectedReplacementCandidate()?.reference],
                                ['Notes', selectedReplacementCandidate()?.notes],
                            ]" :key="row[0]">
                                <div class="flex items-start justify-between gap-4 px-4 py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="row[0]"></dt>
                                    <dd class="text-right text-sm text-gray-900" x-text="row[1] || '-'"></dd>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>
            </div>

            <div class="border-t border-gray-200 px-6 py-4">
                <form
                    method="POST"
                    :action="replacementDialogData?.action || '#'"
                    class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    @csrf
                    <input type="hidden" name="matched_payment_id" :value="selectedReplacementCandidate()?.id || ''">
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-700 sm:mr-auto">
                        <input
                            type="checkbox"
                            name="email_receipt"
                            value="1"
                            class="h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                        >
                        <span>Resend updated receipt to customer</span>
                    </label>
                    <x-ui.button type="button" color="secondary" x-on:click="closeReplacementDialog()">Cancel</x-ui.button>
                    <x-ui.button type="submit" color="dark" x-bind:disabled="!selectedReplacementCandidate()">Replace</x-ui.button>
                </form>
            </div>
        </div>
    </div>
</template>
