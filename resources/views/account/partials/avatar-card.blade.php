@php
    $avatarUser = $avatarUser ?? $user;
    $avatarEditable = $avatarEditable ?? true;
    $avatarMediaSelectable = $avatarMediaSelectable ?? true;
    $avatarCameraEnabled = $avatarCameraEnabled ?? true;
    $avatarPersistenceCapabilities = \App\Models\User::avatarPersistenceCapabilities();
    $avatarPresetSelectionEnabled = $avatarPersistenceCapabilities['preset_selection_enabled'];
    $avatarMediaPersistenceEnabled = $avatarPersistenceCapabilities['media_persistence_enabled'];
    $avatarImageFramingEnabled = $avatarPersistenceCapabilities['image_framing_enabled'];
    $avatarMediaSelectionEnabled = (bool) $avatarEditable && (bool) $avatarMediaSelectable && $avatarMediaPersistenceEnabled;
    $avatarCameraEnabled = (bool) $avatarCameraEnabled && $avatarMediaSelectionEnabled;
    $avatarPickerAvailable = (bool) $avatarEditable && ($avatarPresetSelectionEnabled || $avatarMediaSelectionEnabled);
    $avatarColorOptions = \App\Models\User::avatarColorOptions();
    $avatarIconOptions = \App\Models\User::avatarIconOptions();
    $initialAvatarMediaName = (string) old('avatar_media_name', $avatarUser->avatar_media_name ?? '');
    $initialAvatarMode = (string) old('avatar_mode', $avatarUser->resolvedAvatarMode());
    $initialAvatarLetters = (string) old('avatar_letters', $avatarUser->avatar_letters ?? $avatarUser->resolvedAvatarLetters());
    $initialAvatarIconClass = (string) old('avatar_icon_class', $avatarUser->avatar_icon_class ?? ($avatarUser->resolvedAvatarIconClass() ?? 'fa-solid fa-comments'));
    $initialAvatarBackgroundColor = (string) old('avatar_background_color', $avatarUser->avatar_background_color ?? $avatarUser->resolvedAvatarBackgroundColor());
    $initialAvatarPreviewUrl = $initialAvatarMediaName !== '' ? ($avatarUser->avatarMedia?->thumbnail ?? '') : '';
    $initialAvatarZoom = (int) old('avatar_zoom', $avatarUser->avatar_zoom ?? 100);
    $initialAvatarOffsetX = (int) old('avatar_offset_x', $avatarUser->avatar_offset_x ?? 0);
    $initialAvatarOffsetY = (int) old('avatar_offset_y', $avatarUser->avatar_offset_y ?? 0);
@endphp

<script>
    if (!window.SMAvatarCardPicker) {
        window.SMAvatarCardPicker = {
            expressionString(value) {
                return `'${String(value ?? '').replaceAll('\\', '\\\\').replaceAll('\'', '\\\'')}'`;
            },
            colorSwatches(component, statePath) {
                return component.avatarColorOptions.map((color) => `
                    <button
                        type="button"
                        class="h-8 w-8 rounded-full border-2 transition"
                        :class="((${statePath}.background_color || '#374151').toUpperCase() === ${window.SMAvatarCardPicker.expressionString(color)} ? 'border-gray-900 scale-110' : 'border-white ring-1 ring-gray-200 hover:scale-110')"
                        :style="'background:' + ${window.SMAvatarCardPicker.expressionString(color)}"
                        x-on:click.prevent="${statePath}.background_color = ${window.SMAvatarCardPicker.expressionString(color)}"
                    ></button>
                `).join('');
            },
            lettersTabHtml(component) {
                const statePath = '$store.media.custom_tab_state.avatar_letters';

                return `
                    <div class="mx-auto flex flex-col gap-5">
                        <div class="flex gap-4 w-full">
                            <div class="flex-1 text-left">
                                <label class="ml-2 text-sm font-semibold text-gray-700" for="media-picker-avatar-letters">Letters</label>
                                <input
                                    id="media-picker-avatar-letters"
                                    type="text"
                                    maxlength="3"
                                    class="mt-3 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm uppercase tracking-[0.2em] text-gray-900 outline-none transition focus:border-primary-color"
                                    x-model="${statePath}.letters"
                                    x-on:input="${statePath}.letters = String($event.target.value || '').toUpperCase().replace(/[^A-Z0-9]+/g, '').slice(0, 3)"
                                    :placeholder="${statePath}.default_letters || 'U'"
                                >
                                <p class="mt-2 ml-2 text-xs text-gray-500">Leave blank to use the account initials.</p>
                            </div>
                            <div class="flex h-24 w-24 items-center justify-center rounded-full text-white shadow-sm" :style="'background:' + (${statePath}.background_color || '#374151')">
                                <span class="text-3xl font-semibold tracking-[0.12em]" x-text="${statePath}.letters || ${statePath}.default_letters || 'U'"></span>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-4">
                                <label for="media-picker-avatar-colour-letters" class="text-sm font-semibold text-gray-700">Background colour</label>
                                <input
                                    id="media-picker-avatar-colour-letters"
                                    type="color"
                                    class="h-10 w-14 cursor-pointer rounded border border-gray-200 bg-white p-0.5"
                                    x-model="${statePath}.background_color"
                                >
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                ${window.SMAvatarCardPicker.colorSwatches(component, statePath)}
                            </div>
                        </div>
                    </div>
                `;
            },
            iconTabHtml(component) {
                const statePath = '$store.media.custom_tab_state.avatar_icon';
                const iconButtons = component.avatarIconOptions.map((iconClass) => `
                    <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-2xl border text-lg transition"
                        :class="${statePath}.icon_class === ${window.SMAvatarCardPicker.expressionString(iconClass)} ? 'border-primary-color bg-primary-color/10 text-primary-color' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-color'"
                        x-on:click.prevent="${statePath}.icon_class = ${window.SMAvatarCardPicker.expressionString(iconClass)}"
                    >
                        <i :class="${window.SMAvatarCardPicker.expressionString(iconClass)}"></i>
                    </button>
                `).join('');

                return `
                    <div class="mx-auto flex flex-col gap-5">
                        <div class="flex w-full gap-4">
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-3 flex-1">
                                    <div class="text-sm font-semibold text-gray-700">Icon</div>
                                </div>
                                <div class="flex mt-4 gap-2 pr-1 flex-wrap">
                                    ${iconButtons}
                                </div>
                            </div>
                            <div class="flex h-24 w-24 items-center justify-center rounded-full text-white shadow-sm" :style="'background:' + (${statePath}.background_color || '#374151')">
                                <i :class="${statePath}.icon_class || 'fa-solid fa-comments'" class="text-3xl"></i>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-4">
                                <label for="media-picker-avatar-colour-icon" class="text-sm font-semibold text-gray-700">Background colour</label>
                                <input
                                    id="media-picker-avatar-colour-icon"
                                    type="color"
                                    class="h-10 w-14 cursor-pointer rounded border border-gray-200 bg-white p-0.5"
                                    x-model="${statePath}.background_color"
                                >
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                ${window.SMAvatarCardPicker.colorSwatches(component, statePath)}
                            </div>
                        </div>
                    </div>
                `;
            },
            syncBackground(component, store, activeTabId) {
                const lettersState = store?.custom_tab_state?.avatar_letters;
                const iconState = store?.custom_tab_state?.avatar_icon;

                if (!lettersState || !iconState) {
                    return;
                }

                if (activeTabId === 'avatar_letters') {
                    lettersState.background_color = component.normalizeColor(iconState.background_color || lettersState.background_color || '#374151');
                    return;
                }

                if (activeTabId === 'avatar_icon') {
                    iconState.background_color = component.normalizeColor(lettersState.background_color || iconState.background_color || '#374151');
                }
            },
            buildTabs(component) {
                if (!component.avatarPresetSelectionEnabled) {
                    return [];
                }

                return [
                    {
                        id: 'avatar_letters',
                        label: 'Letters',
                        state: {
                            letters: component.normalizeLetters(component.avatarLetters),
                            default_letters: component.avatarDefaultLetters,
                            background_color: component.normalizeColor(component.avatarBackgroundColor),
                        },
                        panel_html: window.SMAvatarCardPicker.lettersTabHtml(component),
                        onOpen: (store, activeTabId) => window.SMAvatarCardPicker.syncBackground(component, store, activeTabId),
                        onConfirm: (store) => {
                            const state = store?.custom_tab_state?.avatar_letters || {};

                            return {
                                type: 'avatar-preset',
                                mode: 'letters',
                                letters: component.normalizeLetters(state.letters || ''),
                                icon_class: '',
                                background_color: component.normalizeColor(state.background_color || '#374151'),
                            };
                        },
                    },
                    {
                        id: 'avatar_icon',
                        label: 'Icon',
                        state: {
                            icon_class: String(component.avatarIconClass || 'fa-solid fa-comments'),
                            background_color: component.normalizeColor(component.avatarBackgroundColor),
                        },
                        panel_html: window.SMAvatarCardPicker.iconTabHtml(component),
                        onOpen: (store, activeTabId) => window.SMAvatarCardPicker.syncBackground(component, store, activeTabId),
                        onConfirm: (store) => {
                            const state = store?.custom_tab_state?.avatar_icon || {};

                            return {
                                type: 'avatar-preset',
                                mode: 'icon',
                                letters: '',
                                icon_class: String(state.icon_class || 'fa-solid fa-comments'),
                                background_color: component.normalizeColor(state.background_color || '#374151'),
                            };
                        },
                    },
                ];
            },
        };
    }
</script>

<section
    class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
    x-data="{
        avatarEditable: @js((bool) $avatarEditable),
        avatarPresetSelectionEnabled: @js($avatarPresetSelectionEnabled),
        avatarMediaSelectionEnabled: @js($avatarMediaSelectionEnabled),
        avatarImageFramingEnabled: @js($avatarImageFramingEnabled),
        avatarCameraEnabled: @js($avatarCameraEnabled),
        avatarMode: @js($initialAvatarMode),
        avatarLetters: @js($initialAvatarLetters),
        avatarDefaultLetters: @js($avatarUser->resolvedAvatarLetters()),
        avatarIconClass: @js($initialAvatarIconClass),
        avatarBackgroundColor: @js($initialAvatarBackgroundColor !== '' ? $initialAvatarBackgroundColor : '#374151'),
        avatarMediaName: @js($initialAvatarMediaName),
        avatarPreviewUrl: @js($initialAvatarPreviewUrl),
        avatarMediaLabel: '',
        avatarMediaSize: '',
        avatarZoom: {{ max(100, min(250, $initialAvatarZoom)) }},
        avatarOffsetX: {{ max(-50, min(50, $initialAvatarOffsetX)) }},
        avatarOffsetY: {{ max(-50, min(50, $initialAvatarOffsetY)) }},
        avatarDragging: false,
        avatarDragStartX: 0,
        avatarDragStartY: 0,
        avatarDragOriginX: 0,
        avatarDragOriginY: 0,
        avatarDragFrameWidth: 1,
        avatarDragFrameHeight: 1,
        avatarColorOptions: @js($avatarColorOptions),
        avatarIconOptions: @js($avatarIconOptions),
        init() {
            this.avatarLetters = this.normalizeLetters(this.avatarLetters);
            this.avatarDefaultLetters = this.normalizeLetters(this.avatarDefaultLetters) || 'U';
            this.avatarBackgroundColor = this.normalizeColor(this.avatarBackgroundColor);
            this.loadAvatarDetails(this.avatarMediaName);
            window.addEventListener('pointermove', (event) => this.handleAvatarDrag(event));
            window.addEventListener('pointerup', () => this.endAvatarDrag());
            window.addEventListener('pointercancel', () => this.endAvatarDrag());
        },
        normalizeLetters(value) {
            return String(value || '').toUpperCase().replace(/[^A-Z0-9]+/g, '').slice(0, 3);
        },
        normalizeColor(value) {
            const normalized = String(value || '').trim().toUpperCase();
            return /^#[0-9A-F]{6}$/.test(normalized) ? normalized : '#374151';
        },
        previewUsesImage() {
            return this.avatarMode === 'media' && !!this.avatarPreviewUrl;
        },
        previewLetters() {
            return this.normalizeLetters(this.avatarLetters) || this.avatarDefaultLetters;
        },
        avatarSelectionSummary() {
            if (this.avatarMode === 'media' && this.avatarMediaName) {
                return this.avatarMediaLabel || 'Image avatar selected';
            }

            if (this.avatarMode === 'icon') {
                return 'Icon avatar selected';
            }

            return this.avatarLetters ? `Custom letters: ${this.previewLetters()}` : `Default letters: ${this.avatarDefaultLetters}`;
        },
        previewBackgroundStyle() {
            return `background:${this.normalizeColor(this.avatarBackgroundColor)};`;
        },
        avatarStyle() {
            return `transform: translate(${this.avatarOffsetX}%, ${this.avatarOffsetY}%) scale(${(this.avatarZoom / 100).toFixed(2)}); transform-origin: center center;`;
        },
        loadAvatarDetails(mediaName) {
            if (!mediaName || !window.SM?.mediaDetails) {
                if (!mediaName) {
                    this.avatarPreviewUrl = '';
                    this.avatarMediaLabel = '';
                    this.avatarMediaSize = '';
                }

                return;
            }

            window.SM.mediaDetails(mediaName, (details) => {
                this.avatarPreviewUrl = String(details?.thumbnail || '');
                this.avatarMediaLabel = String(details?.name || '');
                this.avatarMediaSize = window.SM?.bytesToString ? window.SM.bytesToString(details?.size || 0) : '';
            });
        },
        setAvatarMode(mode) {
            if ((mode === 'letters' || mode === 'icon') && !this.avatarPresetSelectionEnabled) {
                return;
            }

            if (mode === 'media' && !this.avatarMediaSelectionEnabled) {
                return;
            }

            this.avatarMode = mode;
        },
        avatarPickerTabs() {
            return window.SMAvatarCardPicker.buildTabs(this);
        },
        openAvatarPicker() {
            if (!this.avatarEditable || !window.SMMediaPicker?.open || (!this.avatarPresetSelectionEnabled && !this.avatarMediaSelectionEnabled)) {
                return;
            }

            window.SMMediaPicker.open(this.avatarMediaName || '', {
                require_mime_type: 'image/*',
                allow_multiple: false,
                allow_uploads: this.avatarMediaSelectionEnabled,
                allow_browser: this.avatarMediaSelectionEnabled,
                allow_camera: this.avatarCameraEnabled,
                title: 'Choose Avatar',
                confirm_button_text: 'Apply Avatar',
                initial_tab: this.avatarMode === 'media' && this.avatarMediaSelectionEnabled
                    ? 'browser'
                    : (this.avatarPresetSelectionEnabled ? (this.avatarMode === 'icon' ? 'avatar_icon' : 'avatar_letters') : 'browser'),
                custom_tabs: this.avatarPresetSelectionEnabled ? this.avatarPickerTabs() : [],
            }, (value) => this.handleAvatarPickerResult(value));
        },
        handleAvatarPickerResult(value) {
            if (value && typeof value === 'object' && value.type === 'avatar-preset') {
                if (!this.avatarPresetSelectionEnabled) {
                    return;
                }

                this.avatarMode = value.mode === 'icon' ? 'icon' : 'letters';
                this.avatarLetters = this.normalizeLetters(value.letters || '');
                this.avatarIconClass = String(value.icon_class || this.avatarIconClass || 'fa-solid fa-comments');
                this.avatarBackgroundColor = this.normalizeColor(value.background_color || '#374151');
                this.avatarMediaName = '';
                this.avatarPreviewUrl = '';
                this.avatarMediaLabel = '';
                this.avatarMediaSize = '';
                this.avatarZoom = 100;
                this.avatarOffsetX = 0;
                this.avatarOffsetY = 0;
                return;
            }

            this.setAvatarMedia(value);
        },
        setAvatarMedia(value) {
            this.avatarMediaName = String(value || '');

            if (this.avatarMediaName === '') {
                this.avatarPreviewUrl = '';
                this.avatarMediaLabel = '';
                this.avatarMediaSize = '';
                this.avatarZoom = 100;
                this.avatarOffsetX = 0;
                this.avatarOffsetY = 0;

                if (this.avatarMode === 'media') {
                    this.avatarMode = this.avatarIconClass ? 'icon' : 'letters';
                }

                return;
            }

            this.avatarMode = 'media';
            this.loadAvatarDetails(this.avatarMediaName);
            this.avatarZoom = 100;
            this.avatarOffsetX = 0;
            this.avatarOffsetY = 0;
        },
        startAvatarDrag(event) {
            if (!this.avatarEditable || !this.previewUsesImage()) {
                return;
            }

            const point = event.touches?.[0] || event;
            const rect = event.currentTarget.getBoundingClientRect();
            this.avatarDragging = true;
            this.avatarDragStartX = point.clientX;
            this.avatarDragStartY = point.clientY;
            this.avatarDragOriginX = this.avatarOffsetX;
            this.avatarDragOriginY = this.avatarOffsetY;
            this.avatarDragFrameWidth = rect.width || 1;
            this.avatarDragFrameHeight = rect.height || 1;
        },
        handleAvatarDrag(event) {
            if (!this.avatarDragging) {
                return;
            }

            const point = event.touches?.[0] || event;
            const zoomScale = this.avatarZoom / 100;
            const deltaX = ((point.clientX - this.avatarDragStartX) / this.avatarDragFrameWidth) * 100 / zoomScale;
            const deltaY = ((point.clientY - this.avatarDragStartY) / this.avatarDragFrameHeight) * 100 / zoomScale;
            this.avatarOffsetX = Math.max(-50, Math.min(50, Math.round(this.avatarDragOriginX + deltaX)));
            this.avatarOffsetY = Math.max(-50, Math.min(50, Math.round(this.avatarDragOriginY + deltaY)));
        },
        endAvatarDrag() {
            this.avatarDragging = false;
        },
    }"
>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Avatar</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $avatarEditable ? ($avatarPresetSelectionEnabled ? 'Choose an image, letters, or an icon for your avatar.' : 'Choose an image for your avatar.') : 'Your current avatar appears across discussions and workshop areas.' }}</p>
        </div>
    </div>

    @if($avatarEditable)
        @if($avatarPresetSelectionEnabled)
            <input type="hidden" name="avatar_mode" x-model="avatarMode">
            <input type="hidden" name="avatar_letters" x-model="avatarLetters">
            <input type="hidden" name="avatar_icon_class" x-model="avatarIconClass">
            <input type="hidden" name="avatar_background_color" x-model="avatarBackgroundColor">
        @endif
        @if($avatarMediaPersistenceEnabled)
            <input type="hidden" name="avatar_media_name" x-model="avatarMediaName">
        @endif
        @if($avatarImageFramingEnabled)
            <input type="hidden" name="avatar_zoom" :value="avatarZoom">
            <input type="hidden" name="avatar_offset_x" :value="avatarOffsetX">
            <input type="hidden" name="avatar_offset_y" :value="avatarOffsetY">
        @endif
    @endif

    <div class="mt-6 flex flex-col items-center">
        <div
            class="relative flex h-40 w-40 items-center justify-center overflow-hidden rounded-full border-4 border-white text-white shadow-sm ring-1 ring-gray-200 touch-none select-none"
            :class="avatarEditable && previewUsesImage() ? (avatarDragging ? 'cursor-grabbing bg-gray-200' : 'cursor-grab bg-gray-200') : ''"
            :style="previewUsesImage() ? '' : previewBackgroundStyle()"
            x-on:pointerdown.prevent="startAvatarDrag($event)"
        >
            <template x-if="previewUsesImage()">
                <img
                    :src="avatarPreviewUrl"
                    alt="Avatar preview"
                    class="h-full w-full object-cover"
                    :style="avatarStyle()"
                >
            </template>
            <template x-if="!previewUsesImage() && avatarMode === 'icon'">
                <i :class="avatarIconClass || 'fa-solid fa-comments'" class="text-5xl"></i>
            </template>
            <template x-if="!previewUsesImage() && avatarMode !== 'icon'">
                <span class="text-4xl font-semibold tracking-[0.12em]" x-text="previewLetters()"></span>
            </template>
        </div>

        <div class="mt-5 flex flex-wrap justify-center gap-2">
            @if($avatarPickerAvailable)
                <x-ui.button type="button" color="primary-outline" class="!px-5" x-on:click.prevent="openAvatarPicker()">
                    {{ $avatarPresetSelectionEnabled ? 'Choose Avatar' : 'Select Image' }}
                </x-ui.button>
            @endif
            @if($avatarEditable && $avatarMediaPersistenceEnabled)
                <x-ui.button type="button" color="secondary" class="!px-5" x-show="avatarMediaName" x-on:click.prevent="setAvatarMedia('')">
                    Remove Image
                </x-ui.button>
            @endif
        </div>

        @if(! $avatarPresetSelectionEnabled)
            <p class="mt-2 text-center text-xs text-amber-700">Extra avatar styles are not available on this database yet, so only image avatars can be saved right now.</p>
        @endif
        <div class="mt-2 text-center text-xs text-gray-500" x-show="avatarEditable && avatarMediaSelectionEnabled" x-cloak>Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
        @if ($errors->has('avatar_media_name'))
            <div class="mt-2 text-xs text-red-600">{{ $errors->first('avatar_media_name') }}</div>
        @endif
    </div>

    <div class="mt-6 space-y-4">
        <div class="rounded-2xl bg-gray-50 p-4" x-show="avatarEditable && avatarImageFramingEnabled && avatarMode === 'media' && avatarMediaName" x-cloak>
            <div class="flex items-center justify-between gap-4">
                <label for="avatar_zoom" class="text-sm font-semibold text-gray-700">Image zoom</label>
                <span class="text-xs font-medium text-gray-500" x-text="`${avatarZoom}%`"></span>
            </div>
            <input id="avatar_zoom" type="range" min="100" max="250" step="1" x-model="avatarZoom" class="mt-3 w-full accent-primary-color">
            <p class="mt-3 text-xs text-gray-500">Drag the image inside the circle to choose the visible area.</p>
        </div>

        @if ($errors->has('avatar_letters'))
            <div class="text-xs text-red-600">{{ $errors->first('avatar_letters') }}</div>
        @endif
        @if ($errors->has('avatar_icon_class'))
            <div class="text-xs text-red-600">{{ $errors->first('avatar_icon_class') }}</div>
        @endif
        @if ($errors->has('avatar_background_color'))
            <div class="text-xs text-red-600">{{ $errors->first('avatar_background_color') }}</div>
        @endif
    </div>

    @if ($errors->has('avatar_mode'))
        <div class="mt-2 text-xs text-red-600">{{ $errors->first('avatar_mode') }}</div>
    @endif
</section>
