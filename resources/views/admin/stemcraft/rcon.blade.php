<x-layout>
    <x-mast title="STEMCraft" :tabs="[
        ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
        ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
        ['title' => 'Webhooks', 'route' => route('admin.stemcraft.webhooks.index')],
        ['title' => 'RCON', 'route' => route('admin.stemcraft.rcon.index')],
    ]" />

    <x-container class="mt-8" inner-class="flex flex-col gap-8">
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="max-w-3xl">
                <h2 class="text-xl font-semibold text-gray-900">RCON Console</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600">Send direct Minecraft server commands from admin. This is equivalent to running commands through RCON.</p>
            </div>

            <div class="mt-4 text-sm text-gray-600">
                Connected target:
                <span class="font-mono text-gray-900">{{ $configuredHost }}@if($configuredPort):{{ $configuredPort }}@endif</span>
                <span class="ml-2">
                    <a class="text-primary-color hover:underline" href="{{ route('admin.site_option.index', ['search' => 'minecraft.rcon']) }}">Edit RCON settings</a>
                </span>
            </div>

            <form method="POST" action="{{ route('admin.stemcraft.rcon.execute') }}" class="mt-6">
                @csrf
                <x-ui.input
                    name="command"
                    label="Command"
                    value="{{ old('command', '') }}"
                    info="Example: list, say Server restarting in 5 minutes, gamemode creative PlayerName"
                />
                <div class="mt-5 flex justify-end">
                    <x-ui.button type="submit">Run command</x-ui.button>
                </div>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-gray-900">Last Response</h2>

            @if(trim((string) $lastCommand) !== '')
                <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Command</div>
                    <div class="mt-1 font-mono text-sm text-gray-900 break-all">{{ $lastCommand }}</div>
                </div>
            @endif

            @if($lastError)
                <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $lastError }}</div>
            @elseif($lastOutput !== null)
                <pre class="mt-4 max-h-[28rem] overflow-auto rounded-2xl bg-gray-50 p-4 text-xs text-gray-800 whitespace-pre-wrap">{{ $lastOutput }}</pre>
            @else
                <p class="mt-4 text-sm text-gray-500">No command has been run yet in this session.</p>
            @endif
        </section>
    </x-container>
</x-layout>
