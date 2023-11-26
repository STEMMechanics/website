@props(['user'])

<x-layout>
    <x-banner heading="User" back="account.users.index" />

    <div class="mx-4">
        <form class="mx-auto max-w-[40rem]" method="POST" action="/account/users/{{ $user->id }}">
            <div class="floating-label">
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required />
                <label for="username">Username</label>
            </div>
            @error('username')
                <p class="error">{{ $message }}</p>
            @enderror

            <div class="floating-label">
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required />
                <label for="email">Email</label>
            </div>
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror

            <div>
                <label><input type="checkbox" name="is_under_14" value="1"
                        @if (old('is_under_14', $user->is_under_14)) checked @endif />
                    Under 14 years</label>
            </div>

            <div class="flex justify-end">
                <button class="btn-blue" type="submit">Save</button>
            </div>
        </form>
    </div>
</x-layout>
