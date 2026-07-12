@php
    $isNew = (bool) ($isNew ?? false);
    $childAccountsEnabled = \App\Models\SiteOption::booleanValue('users.child-accounts-enabled', true);
@endphp

<x-layout>
    <x-mast
        backRoute="account.children.index"
        backTitle="{{ $childAccountsEnabled ? 'Child Accounts' : 'Linked Accounts' }}"
        description="{{ $isNew
            ? ($childAccountsEnabled
                ? 'Create a child account with its own username, password, and avatar settings.'
                : 'Create a linked account with its own username, password, and avatar settings.')
            : ($childAccountsEnabled
                ? 'Manage this child account including username, password, and avatar settings.'
                : 'Manage this linked account including username, password, and avatar settings.') }}"
    >
        {{ $isNew ? ($childAccountsEnabled ? 'Create Child Account' : 'Create Linked Account') : ($childAccountsEnabled ? 'Manage Child Account' : 'Manage Linked Account') }}
    </x-mast>

    <x-container inner-class="max-w-6xl">
        <div class="my-8 space-y-6">
            <form
                method="POST"
                action="{{ $isNew ? route('account.children.store') : route('account.children.update', $child) }}"
                id="child-account-settings-form"
                class="my-8"
                x-data="{
                    canSelectAvatarMedia: @js((bool) old('child_can_select_avatar_media', $child->child_can_select_avatar_media ?? true)),
                    canUseAvatarCamera: @js((bool) old('child_can_use_avatar_camera', $child->child_can_use_avatar_camera ?? true)),
                }"
            >
                @csrf
                @unless($isNew)
                    @method('PUT')
                @endunless

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="space-y-6">
                        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Profile Details</h2>
                                    <p class="mt-1 text-sm text-gray-600">Set the username and password this child will use to sign in.</p>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-4 md:grid-cols-2">
                                <x-ui.input class="mb-0" label="Username" name="username" value="{{ old('username', $child->username) }}" info="Must be unique across all accounts." />
                                <x-ui.input class="mb-0" type="password" label="{{ $isNew ? 'Password' : 'New password (optional)' }}" name="password" value="" />
                                <div class="hidden md:block"></div>
                                <x-ui.input class="mb-0" type="password" label="{{ $isNew ? 'Confirm password' : 'Confirm new password' }}" name="password_confirmation" value="" />
                            </div>

                        </section>

                        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Avatar Permissions</h2>
                                <p class="mt-1 text-sm text-gray-600">Control whether this child can change their avatar on their own account page.</p>
                            </div>

                            <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="space-y-3">
                                    <input type="hidden" name="child_can_select_avatar_media" value="0" />
                                    <x-ui.checkbox
                                        label="Allow this child to choose and edit their avatar"
                                        name="child_can_select_avatar_media"
                                        value="1"
                                        checked="{{ old('child_can_select_avatar_media', $child->child_can_select_avatar_media ?? true) }}"
                                        x-model="canSelectAvatarMedia"
                                        x-on:change="if (!canSelectAvatarMedia) { canUseAvatarCamera = false; }"
                                    />
                                    <div class="space-y-3 rounded-2xl bg-white px-4 py-4 ring-1 ring-gray-200" x-bind:class="canSelectAvatarMedia ? '' : 'opacity-50'">
                                        <input type="hidden" name="child_can_use_avatar_camera" value="0" />
                                        <x-ui.checkbox
                                            label="Allow this child to use the camera in the media picker"
                                            name="child_can_use_avatar_camera"
                                            value="1"
                                            checked="{{ old('child_can_use_avatar_camera', $child->child_can_use_avatar_camera ?? true) }}"
                                            x-model="canUseAvatarCamera"
                                            x-bind:disabled="!canSelectAvatarMedia"
                                        />
                                        <p class="text-xs text-gray-500">Camera access is only available when this child is allowed to edit their avatar.</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="grid self-start content-start gap-6 md:grid-cols-2 xl:grid-cols-1">
                        @include('account.partials.avatar-card', [
                            'avatarUser' => $child,
                            'avatarMediaSelectable' => true,
                            'avatarCameraEnabled' => true,
                        ])
                    </div>
                </div>
            </form>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @if(!$isNew)
                    <form
                        method="POST"
                        action="{{ route('account.children.destroy', $child) }}"
                        data-delete-title="{{ $childAccountsEnabled ? 'Delete child account?' : 'Delete linked account?' }}"
                        data-delete-message="{{ $childAccountsEnabled ? 'Are you sure you want to delete this child account? This action cannot be undone.' : 'Are you sure you want to delete this linked account? This action cannot be undone.' }}"
                        data-delete-secondary-message="{{ $childAccountsEnabled ? 'Any workshop tickets for this child account will remain valid.' : 'Any workshop tickets for this linked account will remain valid.' }}"
                        x-data
                        x-on:submit.prevent="SM.confirmAccountDelete($el)"
                    >
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" color="danger-outline">{{ $childAccountsEnabled ? 'Delete child account' : 'Delete linked account' }}</x-ui.button>
                    </form>
                @else
                    <div></div>
                @endif
                <div class="flex gap-3">
                    <x-ui.button type="submit" form="child-account-settings-form">{{ $isNew ? ($childAccountsEnabled ? 'Create child account' : 'Create linked account') : ($childAccountsEnabled ? 'Save child account' : 'Save linked account') }}</x-ui.button>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>
