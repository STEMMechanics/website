<nav class="mb-4 flex items-center justify-between bg-white shadow-sm">
    <a href="/"><img class="injectable w-60 px-4 py-3" src="{{ asset('images/logo.svg') }}" alt=""
            class="logo" /></a>
    <ul class="text mr-6 flex">

        <li class="b-1 z-30 flex justify-center">
            <div x-data="{
                open: false,
                toggle() {
                    if (this.open) {
                        return this.close()
                    }
            
                    this.$refs.button.focus()
            
                    this.open = true
                },
                close(focusAfter) {
                    if (!this.open) return
            
                    this.open = false
            
                    focusAfter && focusAfter.focus()
                }
            }" x-on:keydown.escape.prevent.stop="close($refs.button)"
                x-on:focusin.window="! $refs.panel.contains($event.target) && close()" x-id="['dropdown-button']"
                class="relative">
                <button x-ref="button" x-on:click="toggle()" :aria-expanded="open"
                    :aria-controls="$id('dropdown-button')" type="button"
                    class="flex items-center gap-2 rounded-md bg-blue px-4 py-2 text-white">
                    Menu
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-ref="panel" x-show="open" x-transition.origin.top.left x-on:click.outside="close($refs.button)"
                    :id="$id('dropdown-button')" style="display: none;"
                    class="absolute right-0 mt-2 whitespace-nowrap rounded-md border bg-white p-2 shadow-md">
                    <a href="/workshops"
                        class="text-md flex w-full items-center gap-2 rounded-md px-4 py-2.5 transition hover:bg-blue hover:text-white">
                        <i class="fa-solid fa-paintbrush"></i> Workshops
                    </a>

                    <a href="/workshops"
                        class="text-md flex w-full items-center gap-2 rounded-md px-4 py-2.5 transition hover:bg-blue hover:text-white">
                        <i class="fa-regular fa-newspaper"></i> Blog
                    </a>

                    <hr class="my-2 border-gray-200" />
                    @auth
                        <a href="{{ route('account.index') }}"
                            class="text-md flex w-full items-center gap-2 rounded-md px-4 py-2.5 transition hover:bg-blue hover:text-white">
                            <i class="fa-solid fa-toolbox"></i>My Account
                        </a>
                        <a href="/logout"
                            class="text-md flex w-full items-center gap-2 rounded-md px-4 py-2.5 transition hover:bg-blue hover:text-white">
                            <i class="fa-solid fa-right-from-bracket"></i> Log out
                        </a>
                    @else
                        <a href="/register"
                            class="text-md flex w-full items-center gap-2 rounded-md px-4 py-2.5 transition hover:bg-blue hover:text-white">
                            <i class="fa-solid fa-user-plus"></i> Register
                        </a>
                        <a href="/login"
                            class="text-md flex w-full items-center gap-2 rounded-md px-4 py-2.5 transition hover:bg-blue hover:text-white">
                            <i class="fa-solid fa-right-to-bracket"></i> Log in
                        </a>
                        @endif
                    </div>
                </div>
            </li>
        </ul>
    </nav>
