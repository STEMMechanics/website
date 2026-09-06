<div data-push-root data-user="{{ auth()->id() }}" data-endpoint="{{ route('admin.push-devices.index') }}" data-prompt="{{ request()->routeIs('admin.dashboard') ? '1' : '0' }}">
    <aside data-push-prompt hidden aria-labelledby="push-prompt-title" class="fixed bottom-5 right-5 z-50 w-[calc(100%-2.5rem)] max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl">
        <button type="button" data-push-later aria-label="Dismiss notification prompt" class="absolute right-4 top-3 text-xl text-gray-400">×</button>
        <div class="flex gap-4 pr-4">
            <i class="fa-regular fa-bell mt-1 text-2xl text-primary-color" aria-hidden="true"></i>
            <div><h2 id="push-prompt-title" class="font-semibold text-gray-900">Background Notifications</h2>
                <p class="mt-2 text-sm text-gray-600">Receive task reminders and workplan updates on this device, even when this site is closed.</p>
            </div>
        </div>
        <p data-push-status role="status" class="mt-3 text-sm text-gray-600"></p>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <button type="button" data-push-later class="rounded-lg bg-gray-100 px-4 py-2 text-gray-800">Not now</button>
            <button type="button" data-push-enable class="rounded-lg bg-primary-color px-4 py-2 text-white disabled:opacity-50">Enable</button>
        </div>
        <button type="button" data-push-disable class="mt-3 w-full text-center text-sm text-gray-500">Don't remind me again</button>
    </aside>
</div>
