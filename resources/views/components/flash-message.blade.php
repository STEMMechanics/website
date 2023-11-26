@if (session()->has('message'))
    @php
        $messageType = session('message-type', 'primary'); // Default to 'primary' if 'message_type' is not set
        $messageClasses = [
            'primary' => 'border-blue bg-blue-lighter text-blue-dark',
            'success' => 'border-green bg-green-lighter text-green-dark',
            'danger' => 'border-red bg-red-lighter text-red-dark',
            'warning' => 'border-yellow bg-yellow-lighter text-yellow-dark',
        ];
    @endphp

    <div x-data="{ show: false }" x-init="$nextTick(() => {
        show = true;
        setTimeout(() => show = false, 7000)
    })" x-show="show"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-300 transform"
        x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-4"
        class="{{ $messageClasses[$messageType] }} fixed left-1/2 top-4 min-w-[15rem] max-w-[20rem] -translate-x-1/2 rounded border px-4 py-2 text-center shadow-lg">
        <p class="text-sm">
            {{ session('message') }}
        </p>
    </div>
@endif
