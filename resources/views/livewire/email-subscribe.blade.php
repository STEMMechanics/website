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

    @if($this->message)
        @if($this->success)
            <div class="mt-5 mx-auto max-w-xl rounded-2xl border border-sky-200 bg-white px-4 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                        <i class="fa fa-check text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $this->message }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-5 mx-auto max-w-xl rounded-2xl border border-rose-200 bg-white px-4 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                        <i class="fa fa-exclamation-triangle text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $this->message }}</p>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
