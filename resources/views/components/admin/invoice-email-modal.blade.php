<div
    x-show="invoiceEmailModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-on:keydown.escape.window="closeInvoiceEmailModal()"
>
    <div
        class="w-full rounded-lg bg-white p-4 shadow-lg transition-all"
        x-bind:class="invoiceEmailHelpOpen ? 'max-w-5xl' : 'max-w-2xl'"
    >
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold">Email Invoice</h3>
                <div class="mt-1 text-xs text-gray-500">
                    Invoice: <span x-text="invoiceEmailInvoiceNumber || 'Unknown'"></span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"
                    title="Show placeholder help"
                    x-on:click.prevent="invoiceEmailHelpOpen = !invoiceEmailHelpOpen"
                    x-bind:aria-pressed="invoiceEmailHelpOpen ? 'true' : 'false'"
                >
                    <i class="fa-solid fa-circle-info"></i>
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 hover:text-black"
                    title="Close"
                    x-on:click.prevent="closeInvoiceEmailModal()"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div
            class="grid gap-6"
            x-bind:class="invoiceEmailHelpOpen ? 'lg:grid-cols-[minmax(0,1fr)_18rem]' : 'lg:grid-cols-1'"
        >
            <form method="POST" x-bind:action="invoiceEmailAction" class="min-w-0">
                @csrf
                <div class="grid gap-4">
                    <div>
                        <label class="block text-sm pl-1" for="invoice-email-recipient-emails">Recipient Email</label>
                        <input
                            id="invoice-email-recipient-emails"
                            name="recipient_emails"
                            type="text"
                            autocomplete="email"
                            data-bwignore="true"
                            class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('recipient_emails') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                            x-model="invoiceEmailRecipientEmails"
                            placeholder="name@example.com, another@example.com"
                        />
                        <div class="text-xs text-gray-500 ml-2 mt-1">Use commas or semicolons to email multiple recipients.</div>
                        @if($errors->has('recipient_emails'))
                            <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('recipient_emails') }}</div>
                        @endif
                    </div>

                    <div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 py-1 text-left"
                            x-on:click.prevent="invoiceEmailSubjectOpen = !invoiceEmailSubjectOpen"
                        >
                            <i class="fa-solid text-xs text-gray-500" x-bind:class="invoiceEmailSubjectOpen ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                            <span class="text-sm font-medium text-gray-900">Subject</span>
                        </button>
                        <div x-show="invoiceEmailSubjectOpen" x-cloak class="mt-2">
                            <input
                                id="invoice-email-subject-line"
                                type="text"
                                autocomplete="off"
                                data-bwignore="true"
                                class="disabled:bg-gray-100 bg-white block px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('subject_line') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                                x-model="invoiceEmailSubjectLine"
                                placeholder="Your Invoice @{{id}} from STEMMechanics"
                            />
                            <input type="hidden" name="subject_line" x-bind:value="invoiceEmailSubjectLine">
                            @if($errors->has('subject_line'))
                                <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('subject_line') }}</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 py-1 text-left"
                            x-on:click.prevent="invoiceEmailCcOpen = !invoiceEmailCcOpen"
                        >
                            <i class="fa-solid text-xs text-gray-500" x-bind:class="invoiceEmailCcOpen ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                            <span class="text-sm font-medium text-gray-900">CC Email</span>
                        </button>
                        <div x-show="invoiceEmailCcOpen" x-cloak class="mt-2">
                            <input
                                id="invoice-email-cc-emails"
                                name="cc_emails"
                                type="text"
                                autocomplete="email"
                                data-bwignore="true"
                                class="disabled:bg-gray-100 bg-white block px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('cc_emails') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                                x-model="invoiceEmailCcEmails"
                                placeholder="cc@example.com, team@example.com"
                            />
                            <div class="text-xs text-gray-500 ml-2 mt-1">Use commas or semicolons to add multiple CC recipients.</div>
                            @if($errors->has('cc_emails'))
                                <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('cc_emails') }}</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm pl-1" for="invoice-email-message">Message</label>
                            <textarea
                                id="invoice-email-message"
                                name="email_message"
                                rows="8"
                                class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border {{ $errors->has('email_message') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                                x-model="invoiceEmailMessage"
                                placeholder="Compose the full email body. Supports placeholders like @{{name}}, @{{id}}, and @{{pay}}."
                            ></textarea>
                        @if($errors->has('email_message'))
                            <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('email_message') }}</div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" color="secondary" x-on:click.prevent="closeInvoiceEmailModal()">Cancel</x-ui.button>
                        <x-ui.button type="submit">Send Invoice Email</x-ui.button>
                    </div>
                </div>
            </form>

            <aside
                x-show="invoiceEmailHelpOpen"
                x-cloak
                class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
            >
                <div class="flex items-center justify-between gap-3">
                    <h4 class="font-semibold text-slate-900">Placeholder Help</h4>
                    <span class="text-xs uppercase tracking-wide text-slate-500">Optional</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">
                    Subject and message fields can use the same placeholder values. The message also supports the payment button token.
                </p>
                <div class="mt-4 space-y-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</div>
                        <ul class="mt-2 space-y-1">
                            <li><code>@{{name}}</code> - recipient first name</li>
                            <li><code>@{{id}}</code> - invoice number</li>
                            <li><code>@{{total}}</code> - invoice total</li>
                            <li><code>@{{outstanding}}</code> - outstanding amount</li>
                            <li><code>@{{due}}</code> - due date</li>
                            <li><code>@{{po}}</code> - purchase order number</li>
                        </ul>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</div>
                        <ul class="mt-2 space-y-1">
                            <li><code>@{{name}}</code> - recipient first name</li>
                            <li><code>@{{id}}</code> - invoice number</li>
                            <li><code>@{{total}}</code> - invoice total</li>
                            <li><code>@{{outstanding}}</code> - outstanding amount</li>
                            <li><code>@{{due}}</code> - due date</li>
                            <li><code>@{{po}}</code> - purchase order number</li>
                            <li><code>@{{pay}}</code> - View and Pay Invoice button</li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
