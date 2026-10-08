<div
    x-data="stockAssemblyDialog()"
    x-init="openInitialAssembly()"
    x-on:open-workshop-assembly.window="openAssemblyDialog($event.detail?.url)"
>
    <dialog
        x-ref="assemblyDialog"
        class="m-auto max-h-[92vh] w-[min(76rem,calc(100%-1rem))] overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 shadow-2xl backdrop:bg-slate-900/50 sm:p-6"
        aria-labelledby="assembly-dialog-title"
        x-on:input="handleAssemblyDialogInput($event)"
        x-on:submit="submitAssemblyDialog($event)"
        x-on:cancel.prevent="closeAssemblyDialog()"
        x-on:click.self="closeAssemblyDialog()"
        x-on:click="if ($event.target.closest('[data-close-assembly-dialog]')) closeAssemblyDialog()"
    >
        <p x-cloak x-show="assemblyDialogLoading" x-text="$refs.assemblyDialogContent?.querySelector('form') ? 'Updating material quantities…' : 'Loading assembly plan…'" class="mb-3 text-xs text-slate-500" role="status"></p>
        <p x-cloak x-show="assemblyDialogError" x-text="assemblyDialogError" class="mb-3 text-sm text-red-700" role="alert"></p>
        <div x-ref="assemblyDialogContent"></div>
    </dialog>
</div>
