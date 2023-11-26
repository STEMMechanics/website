<x-layout>
    <x-card class="relative mx-auto mt-12 max-w-lg shadow-lg">
        <header class="relative">
            <h2 class="-m-6 mb-6 rounded-t-lg bg-green px-6 py-4 text-xl text-white">Sign up to STEMMechanics</h2>
            <a href="?reset=1" class="text-white transition hover:text-red" title="Restart registration"><i
                    class="fa-solid fa-xmark absolute right-0 top-0 translate-y-1/2 text-xl"></i></a>
        </header>

        <form class="m-0" method="POST" action="/register" x-data="{ age: '' }">
            <input type="hidden" name="form_step" value="{{ $form->currentStep() }}">
            @csrf

            @switch($form->currentStep())
                @case(1)
                    <div class="floating-label mb-6 mt-12">
                        <input type="text" class="w-full rounded border border-gray-200 p-2" name="username"
                            value="{{ $form->getValue('username') }}" required autofocus />
                        <label for="username" class="mx-1 mb-1 inline-block text-sm text-gray-800">Choose a username</label>
                        @error('username')
                            <p class="error">{{ $errors->first('username') }}</p>
                        @enderror
                    </div>

                    <div class="floating-label my-8">
                        <input type="password" class="w-full rounded border border-gray-200 p-2" name="password"
                            value="{{ $form->getValue('password') }}" placeholder="Password" />
                        <label for="password">Choose a password</label>
                        @error('password')
                            <p class="error">{{ $errors->first('password') }}</p>
                        @enderror
                        <p class="mt-1 px-1 text-xs text-gray-400">Required to be at least 8 characters and include a
                            number</p>
                    </div>
                @break

                @case(2)
                    <p>Are you over or under 14 years old?</p>

                    <input type="hidden" name="age" x-model="age">
                    <button x-on:click="age = $event.target.value" type="submit" value="under" class="btn mt-8 w-full"
                        tabindex="1">I
                        am under 14</button>
                    <button x-on:click="age = $event.target.value" x-data="" value="over" type="submit"
                        class="btn my-6 w-full" tabindex="2">I am 14 or older</button>
                @break

                @case(3)
                    @if ($form->getValue('age') == 'over')
                        <p>Please enter your email address so we can verify your account</p>
                    @else
                        <p>Please find a parent or guardian's email address, and we can verify your account</p>
                    @endif

                    <div class="floating-label my-6">
                        <input type="email" class="w-full rounded border border-gray-200 p-2" name="email"
                            value="{{ old('email') }}" required autocomplete="off" spellcheck="false" autocorrect="off"
                            autofocus />
                        @if ($form->getValue('age') == 'over')
                            <label for="email" class="mb-1 inline-block text-sm text-gray-800">Your email</label>
                        @else
                            <label for="email" class="mb-1 inline-block text-sm text-gray-800">Parent or guardian's
                                email</label>
                        @endif
                        @error('email')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>
                @break

                @case(4)
                    @include('partials.email-verify', ['resend' => true])
                @break

            @endswitch

            @if ($form->currentStep() < 4)
                <div class="flex items-center justify-between">
                    @if ($form->currentStep() == 1)
                        <small class="text-xs text-gray-600">Already have an account? <a href="{{ route('login') }}"
                                class="text-blue transition hover:text-blue-dark">Login</a></small>
                    @elseif ($form->currentStep() < 4)
                        <a href="{{ route('register', ['form_step' => $form->currentStep() - 1]) }}"
                            class="text-blue transition hover:text-blue-dark">
                            <i class="fa-solid fa-angle-left mr-2"></i>Back</a>
                    @endif

                    @if ($form->currentStep() == 1)
                        <button type="submit"
                            class="rounded bg-green px-8 py-2 text-white transition hover:bg-green-dark">
                            Next<i class="fa-solid fa-angle-right ml-2"></i>
                        </button>
                    @elseif ($form->currentStep() == 3)
                        <button type="submit"
                            class="rounded bg-green px-8 py-2 text-white transition hover:bg-green-dark">
                            Verify
                        </button>
                    @endif
                </div>
            @endif

            {{ $form->getValue('error') }}
        </form>
    </x-card>
</x-layout>
