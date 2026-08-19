<x-layout>
    <x-mast backRoute="admin.server.backups" backTitle="Backups">{{ $title }}</x-mast>

    <x-container>
        <div class="mx-auto my-8 max-w-2xl rounded-xl border border-sky-200 bg-sky-50 p-6 shadow-sm" x-data="backupPreparation(@js($run), @js($statusUrl), @js($returnUrl))" x-init="start()">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-hard-drive text-2xl text-sky-700"></i>
                <div>
                    <h2 class="text-lg font-semibold text-sky-950">Preparing backup contents</h2>
                    <p class="mt-1 text-sm text-sky-800" x-text="run.status === 'failed' ? run.error_message : run.message"></p>
                </div>
            </div>
            <div x-show="!run.finished" class="mt-5 h-3 overflow-hidden rounded-full bg-white">
                <div class="h-full animate-pulse rounded-full bg-sky-600 transition-all duration-700" :style="`width:${Math.max(5, Number(run.progress || 0))}%`"></div>
            </div>
            <p x-show="!run.finished" class="mt-3 text-xs text-sky-700">You can leave this page. It will open the backup automatically when preparation is complete.</p>
        </div>
    </x-container>
</x-layout>

<script>
    window.backupPreparation = (initialRun, statusUrl, returnUrl) => ({
        run: initialRun,
        start() { this.poll(); },
        poll() {
            window.setTimeout(async () => {
                try {
                    const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok || !payload.run) throw new Error('Unable to read preparation status.');
                    this.run = payload.run;
                    if (this.run.status === 'completed') window.location.assign(returnUrl);
                    else if (!this.run.finished) this.poll();
                } catch (error) {
                    this.poll();
                }
            }, 1500);
        },
    });
</script>
