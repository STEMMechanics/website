<div class="relative inline-flex size-8 items-center justify-center rounded-md text-slate-600 hover:bg-slate-200 hover:text-slate-900 focus-within:ring-2 focus-within:ring-primary-color" title="Insert workshop placeholder">
    <span class="pointer-events-none inline-flex items-center gap-1" aria-hidden="true">
        <i class="fa-solid fa-key text-xs"></i>
        <i class="fa-solid fa-chevron-down text-[9px]"></i>
    </span>
    <select
        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
        aria-label="Insert workshop placeholder"
        x-on:change="if ($event.target.value) { insertText($event.target.value); $event.target.value = ''; }"
    >
        <option value="">Insert workshop placeholder</option>
        <option value="{date-short}">{date-short} · 27/08/2026</option>
        <option value="{date-long}">{date-long} · Thursday 27 August</option>
        <option value="{date-ddd dd/mm/yyyy}">{date-ddd dd/mm/yyyy} · Thu 27/08/2026</option>
        <option value="{start-time}">{start-time}</option>
        <option value="{end-time}">{end-time}</option>
        <option value="{time-range}">{time-range}</option>
        <option value="{location}">{location}</option>
        <option value="{ages}">{ages}</option>
        <option value="{cost}">{cost}</option>
        <option value="{workshop-url}">{workshop-url}</option>
    </select>
</div>
