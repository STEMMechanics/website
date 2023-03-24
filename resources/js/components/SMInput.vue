<template>
    <div
        :class="[
            'sm-input-group',
            {
                'sm-input-active': inputActive,
                'sm-feedback-invalid': feedbackInvalid,
                'sm-input-small': small,
            },
            computedClassType,
        ]">
        <label v-if="label">{{ label }}</label>
        <ion-icon
            class="sm-invalid-icon"
            name="alert-circle-outline"></ion-icon>
        <input
            v-if="
                type == 'text' ||
                type == 'email' ||
                type == 'password' ||
                type == 'email' ||
                type == 'url' ||
                type == 'daterange' ||
                type == 'datetime'
            "
            :type="type"
            :value="value"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown" />
        <textarea
            v-else-if="type == 'textarea'"
            rows="5"
            :value="value"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown"></textarea>
        <div v-else-if="type == 'file'" class="sm-input-file-group">
            <input
                id="file"
                type="file"
                class="sm-file"
                :accept="props.accept"
                @change="handleChange" />
            <label class="sm-button" for="file">Select file</label>
            <div class="sm-file-name">
                {{ modelValue?.name ? modelValue.name : modelValue }}
            </div>
        </div>
        <select
            v-else-if="type == 'select'"
            :value="value"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown">
            <option
                v-for="(optionValue, key) in options"
                :key="key"
                :value="key"
                :selected="key == value">
                {{ optionValue }}
            </option>
        </select>
        <div v-else-if="type == 'media'" class="sm-input-media">
            <div class="sm-input-media-item">
                <img v-if="mediaUrl.length > 0" :src="mediaUrl" />
                <ion-icon v-else name="image-outline" />
            </div>
            <a
                class="sm-button sm-button-small"
                @click.prevent="handleMediaSelect"
                >Select file</a
            >
        </div>
        <div v-if="slots.default || feedbackInvalid" class="sm-input-help">
            <span v-if="feedbackInvalid" class="sm-input-invalid">{{
                feedbackInvalid
            }}</span>
            <span v-if="slots.default" class="sm-input-info">
                <slot></slot>
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, inject, ref, useSlots, watch } from "vue";
import { openDialog } from "./SMDialog";
import { api } from "../helpers/api";
import { MediaResponse } from "../helpers/api.types";
import { imageMedium } from "../helpers/image";
import { toTitleCase } from "../helpers/string";
import { isEmpty } from "../helpers/utils";
import { isUUID } from "../helpers/uuid";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
        required: false,
    },
    label: {
        type: String,
        default: "",
        required: false,
    },
    type: {
        type: String,
        default: "text",
    },
    small: {
        type: Boolean,
        default: false,
        required: false,
    },
    feedbackInvalid: {
        type: String,
        default: "",
    },
    accept: {
        type: String,
        default: "",
    },
    options: {
        type: Object,
        default() {
            return {};
        },
    },
    control: {
        type: [String, Object],
        default: "",
    },
    form: {
        type: Object,
        default: () => {
            return {};
        },
        required: false,
    },
});

const emits = defineEmits(["update:modelValue", "focus", "blur", "keydown"]);
const slots = useSlots();
const mediaUrl = ref("");

const objForm = inject("form", props.form);
const objControl =
    typeof props.control == "object"
        ? props.control
        : !isEmpty(objForm) &&
          typeof props.control == "string" &&
          props.control != ""
        ? objForm.controls[props.control]
        : null;

const label = ref(props.label);
const feedbackInvalid = ref(props.feedbackInvalid);
const value = ref(props.modelValue);
const inputActive = ref(value.value.length > 0 || props.type == "select");

/**
 * Return the classname based on type
 */
const computedClassType = computed(() => {
    return `sm-input-type-${props.type}`;
});

watch(
    () => props.label,
    (newValue) => {
        label.value = newValue;
    }
);

if (objControl) {
    if (value.value.length > 0) {
        objControl.value = value.value;
    } else {
        value.value = objControl.value;
    }

    if (label.value.length == 0 && typeof props.control == "string") {
        label.value = toTitleCase(props.control);
    }

    inputActive.value = value.value?.length > 0 || props.type == "select";

    watch(
        () => objControl.validation.result.valid,
        (newValue) => {
            feedbackInvalid.value = newValue
                ? ""
                : objControl.validation.result.invalidMessages[0];
        },
        { deep: true }
    );

    watch(
        () => objControl.value,
        (newValue) => {
            value.value = newValue;
        },
        { deep: true }
    );
}

watch(
    () => props.modelValue,
    (newValue) => {
        value.value = newValue;
    }
);

watch(
    () => props.feedbackInvalid,
    (newValue) => {
        feedbackInvalid.value = newValue;
    }
);

watch(
    () => value.value,
    async (newValue) => {
        inputActive.value = newValue.length > 0;

        if (props.type == "media") {
            if (isUUID(newValue)) {
                try {
                    const result = await api.get({
                        url: "/media/{id}",
                        params: {
                            id: newValue,
                        },
                    });

                    const data = result.data as MediaResponse;

                    if (data && data.medium) {
                        mediaUrl.value = imageMedium(data.medium.url);
                    }
                } catch (error) {
                    /* empty */
                }
            }
        }
    }
);

const handleChange = (event) => {
    emits("update:modelValue", event.target.files[0]);
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    value.value = target.value;
    emits("update:modelValue", target.value);

    if (objControl) {
        objControl.value = target.value;
        feedbackInvalid.value = "";
    }
};

const handleFocus = (event: Event) => {
    inputActive.value = true;

    if (event instanceof KeyboardEvent) {
        if (event.key === undefined || event.key === "Tab") {
            emits("blur", event);
        }
    }

    emits("focus", event);
};

const handleBlur = async (event: Event) => {
    if (objControl) {
        await objControl.validate();
        objControl.isValid();
    }

    const target = event.target as HTMLInputElement;

    if (target.value.length == 0) {
        inputActive.value = false;
    }

    emits("blur", event);
};

const handleKeydown = (event: Event) => {
    emits("keydown", event);
};

const handleMediaSelect = async (event) => {
    let result = await openDialog(SMDialogMedia);
    if (result) {
        mediaUrl.value = result.url;
        emits("update:modelValue", result.id);

        if (objControl) {
            objControl.value = result.id;
            feedbackInvalid.value = "";
        }
    }
};
</script>

<style lang="scss">
.sm-column .sm-input-group {
    margin-bottom: 0;
}

.sm-input-group {
    position: relative;
    display: flex;
    flex-direction: column;
    margin-bottom: map-get($spacer, 4);
    flex: 1;
    width: 100%;

    &.sm-input-small {
        font-size: 80%;

        &.sm-input-active {
            label {
                transform: translate(6px, -3px) scale(0.7);
            }

            input {
                padding: calc(#{map-get($spacer, 1)} * 1.5) map-get($spacer, 2)
                    calc(#{map-get($spacer, 1)} / 2) map-get($spacer, 2);
            }
        }

        input,
        label {
            padding: map-get($spacer, 1) map-get($spacer, 2);
        }
    }

    &.sm-input-active {
        label {
            transform: translate(8px, -3px) scale(0.7);
            color: $secondary-color-dark;
        }

        input {
            padding: calc(#{map-get($spacer, 2)} * 1.5) map-get($spacer, 3)
                calc(#{map-get($spacer, 2)} / 2) map-get($spacer, 3);
        }

        textarea {
            padding: calc(#{map-get($spacer, 2)} * 2) map-get($spacer, 3)
                calc(#{map-get($spacer, 2)} / 2) map-get($spacer, 3);
        }

        select {
            padding: calc(#{map-get($spacer, 2)} * 2) map-get($spacer, 3)
                calc(#{map-get($spacer, 2)} / 2) map-get($spacer, 3);
        }
    }

    &.sm-feedback-invalid {
        input,
        select,
        textarea {
            border: 2px solid $danger-color;
        }

        .sm-invalid-icon {
            display: block;
        }
    }

    label {
        position: absolute;
        display: block;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        line-height: 1.5;
        transform-origin: top left;
        transform: translate(0, 1px) scale(1);
        transition: all 0.1s ease-in-out;
        color: $secondary-color-dark;
        pointer-events: none;
    }

    .sm-invalid-icon {
        position: absolute;
        display: none;
        right: 0;
        top: 2px;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        color: $danger-color;
        font-size: 120%;
    }

    &.sm-input-select {
        .sm-invalid-icon {
            display: none;
        }
    }

    input,
    select,
    textarea {
        box-sizing: border-box;
        display: block;
        width: 100%;
        border: 1px solid $border-color;
        border-radius: 12px;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        color: $font-color;
        margin-bottom: map-get($spacer, 1);

        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    textarea {
        resize: none;
    }

    select {
        padding-right: 2.5rem;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 24px 18px;
    }

    &.sm-input-type-media {
        label {
            position: relative;
            transform: none;
        }

        .sm-input-help {
            text-align: center;
        }

        &.sm-feedback-invalid .sm-input-media .sm-input-media-item ion-icon {
            border: 2px solid $danger-color;
        }

        &.sm-feedback-invalid .sm-invalid-icon {
            // position: relative;
        }
    }

    .sm-input-media {
        text-align: center;
        margin-bottom: map-get($spacer, 2);

        .sm-input-media-item {
            display: block;
            margin-bottom: 0.5rem;

            img {
                max-width: 100%;
                max-height: 100%;
            }

            ion-icon {
                padding: 4rem;
                font-size: 3rem;
                border: 1px solid $border-color;
                background-color: #fff;
            }
        }

        .button {
            display: inline-block;
        }
    }

    .sm-input-help {
        font-size: 75%;
        margin: 0 map-get($spacer, 1);
        color: $secondary-color-dark;

        .sm-input-invalid {
            color: $danger-color;
            padding-right: map-get($spacer, 1);
        }
    }

    .sm-input-file-group {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
        border: 1px solid transparent;
        border-radius: 12px;

        input {
            opacity: 0;
            width: 0.1px;
            height: 0.1px;
            position: absolute;
            margin-left: -9999px;
        }

        label.button {
            margin-right: map-get($spacer, 4);
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            margin: 0;
            height: 3rem;
            width: auto;
        }

        .sm-file-name {
            display: block;
            border: 1px solid $border-color;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
            flex: 1;
            height: 3rem;
            background-color: #fff;
            line-height: 3rem;
            padding: 0 1rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
}
</style>
