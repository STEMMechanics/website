<section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Profile Photo</h2>
            <p class="mt-1 text-sm text-gray-600">Choose how your avatar appears across your account and discussions.</p>
        </div>
    </div>

    <input type="hidden" name="avatar_media_name" x-model="avatarMediaName" x-ref="avatarMediaInput">
    <input type="hidden" name="avatar_zoom" :value="avatarZoom">
    <input type="hidden" name="avatar_offset_x" :value="avatarOffsetX">
    <input type="hidden" name="avatar_offset_y" :value="avatarOffsetY">

    <div class="mt-6 flex flex-col items-center">
        <div
            class="relative h-40 w-40 overflow-hidden rounded-full border-4 border-white bg-gray-200 shadow-sm ring-1 ring-gray-200 touch-none select-none"
            :class="avatarPreviewUrl ? (avatarDragging ? 'cursor-grabbing' : 'cursor-grab') : ''"
            x-on:pointerdown.prevent="startAvatarDrag($event)"
        >
            <template x-if="avatarPreviewUrl">
                <img
                    :src="avatarPreviewUrl"
                    alt="Avatar preview"
                    class="h-full w-full object-cover"
                    :style="avatarStyle()"
                >
            </template>
            <template x-if="!avatarPreviewUrl">
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-600 to-gray-800 text-4xl font-semibold text-white">
                    {{ strtoupper(substr($user->username ?: $user->getName(), 0, 1)) }}
                </div>
            </template>
        </div>

        <div class="mt-5 flex flex-wrap justify-center gap-2">
            <x-ui.button type="button" color="primary-outline" class="!px-5" x-on:click.prevent="openAvatarPicker()">
                Select Image
            </x-ui.button>
            <x-ui.button type="button" color="secondary" class="!px-5" x-show="avatarMediaName" x-on:click.prevent="setAvatarMedia('')">
                Remove
            </x-ui.button>
        </div>

        <div class="mt-3 text-center text-sm text-gray-500" x-text="avatarMediaLabel"></div>
        <div class="text-center text-xs text-gray-500" x-text="avatarMediaSize"></div>
        <div class="mt-2 text-center text-xs text-gray-500">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
        @if ($errors->has('avatar_media_name'))
            <div class="mt-2 text-xs text-red-600">{{ $errors->first('avatar_media_name') }}</div>
        @endif
    </div>

    <div class="mt-6 rounded-2xl bg-gray-50 p-4">
        <div class="flex items-center justify-between gap-4">
            <label for="avatar_zoom" class="text-sm font-semibold text-gray-700">Zoom</label>
            <span class="text-xs font-medium text-gray-500" x-text="`${avatarZoom}%`"></span>
        </div>
        <input id="avatar_zoom" type="range" min="100" max="250" step="1" x-model="avatarZoom" class="mt-3 w-full accent-primary-color">
        <p class="mt-3 text-xs text-gray-500">Drag the image inside the circle to choose the visible area. Removing it here only clears the profile setting.</p>
    </div>
</section>
