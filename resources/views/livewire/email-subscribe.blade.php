<div>
    <form wire:submit.prevent="subscribe" class="flex flex-row justify-center">
        <input
                type="text"
                name="name"
                wire:model.defer="trap"
                autocomplete="off"
                tabindex="-1"
                class="hidden"
        />

        <x-ui.input
                type="email"
                name="email"
                label="Email"
                no-label
                wire:model.defer="email"
                class="m-0"
                field-classes="rounded-r-none sm:w-96"
        />

        {{-- Submit button --}}
        <x-ui.button color="dark" type="submit" class="rounded-l-none">
            Subscribe
        </x-ui.button>
    </form>

    @if($message)
        @if($success)
            <p class="mt-4 text-sm text-green-600 mx-auto border-green-800 bg-green-100 py-1 px-4 w-fit">
                <i class="fa fa-check mr-2"></i>{{ $message }}
            </p>
        @else
            <p class="mt-4 text-sm text-red-600 mx-auto border-red-800 bg-red-100 py-1 px-4 w-fit">
                <i class="fa fa-exclamation-triangle mr-2"></i>{{ $message }}
            </p>
        @endif
    @endif
</div>