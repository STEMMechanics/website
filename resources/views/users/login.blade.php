<x-layout>
    <x-card class="relative z-40 mx-auto mt-12 max-w-lg shadow-lg">
        <header>
            <h2 class="-m-6 mb-10 rounded-t-lg bg-orange px-6 py-4 text-xl text-white">Log in to STEMMechanics</h2>
        </header>

        <form method="POST" action="/login">
            @csrf

            <div class="floating-label my-8">
                <input type="text" class="w-full rounded border border-gray-200 p-2" name="username"
                    value="{{ old('username') }}" required />
                <label for="username" class="mb-1 inline-block text-sm text-gray-800">Username</label>

                @error('username')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="floating-label my-8">
                <input type="password" class="w-full rounded border border-gray-200 p-2" name="password"
                    value="{{ old('password') }}" placeholder="Password" />
                <label for="password">Password</label>
                @error('password')
                    <p class="error">{{ $message }}</p>
                @enderror
                <p class="mt-1 px-1 text-xs"><a href="/forgot-password"
                        class="text-blue transition hover:text-blue-dark">
                        Forgot Password</a></p>
            </div>

            <div class="flex items-end justify-between">
                <p class="text-xs text-gray-600">Need an account?
                    <a href="/register" class="text-blue transition hover:text-blue-dark">
                        Register</a>
                </p>
                <button type="submit" class="rounded bg-orange px-8 py-2 text-white transition hover:bg-orange-dark">
                    Login
                </button>
            </div>
        </form>
    </x-card>
</x-layout>
