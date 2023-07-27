<template>
    <div class="flex flex-col flex-1 flex-align-center">
        <label class="control-label" v-bind="{ for: id }">{{ label }}</label>
        <div v-if="mediaUrl?.length > 0" class="text-center mb-4">
            <img
                class="max-w-48 max-h-48"
                :src="mediaGetThumbnail(value, 'medium')" />
        </div>
        <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 -960 960 960"
            class="h-48 text-gray">
            <path
                d="M180-120q-24 0-42-18t-18-42v-600q0-24 18-42t42-18h600q24 0 42 18t18 42v600q0 24-18 42t-42 18H180Zm0-60h600v-600H180v600Zm56-97h489L578-473 446-302l-93-127-117 152Zm-56 97v-600 600Zm160.118-390Q361-570 375.5-584.618q14.5-14.617 14.5-35.5Q390-641 375.382-655.5q-14.617-14.5-35.5-14.5Q319-670 304.5-655.382q-14.5 14.617-14.5 35.5Q290-599 304.618-584.5q14.617 14.5 35.5 14.5Z"
                fill="currentColor" />
        </svg>
        <div class="text-center">
            <button
                type="button"
                class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                :disabled="disabled"
                @click="handleMediaSelect">
                Select File
            </button>
        </div>
        <template v-if="slots.help"><slot name="help"></slot></template>
    </div>
</template>

<script setup lang="ts">
import { inject, watch, ref, useSlots, computed } from "vue";
import { isEmpty, generateRandomElementId } from "../helpers/utils";
import { toTitleCase } from "../helpers/string";
import { mediaGetThumbnail } from "../helpers/media";
import { openDialog } from "./SMDialog";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";
import { Media } from "../helpers/api.types";

const emits = defineEmits(["update:modelValue", "blur", "keyup"]);
const props = defineProps({
    form: {
        type: Object,
        default: undefined,
        required: false,
    },
    control: {
        type: [String, Object],
        default: "",
    },
    label: {
        type: String,
        default: undefined,
        required: false,
    },
    modelValue: {
        type: [String, Number, Boolean],
        default: undefined,
        required: false,
    },
    type: {
        type: String,
        default: "text",
        required: false,
    },
    id: {
        type: String,
        default: undefined,
        required: false,
    },
    disabled: {
        type: Boolean,
        default: false,
        required: false,
    },
    button: {
        type: String,
        default: "",
        required: false,
    },
    showClear: {
        type: Boolean,
        default: false,
        required: false,
    },
    feedbackInvalid: {
        type: String,
        default: "",
        required: false,
    },
    autofocus: {
        type: Boolean,
        default: false,
        required: false,
    },
    accepts: {
        type: String,
        default: "image/*",
        required: false,
    },
    options: {
        type: Object,
        default: null,
        required: false,
    },
    size: {
        type: String,
        default: "",
        required: false,
    },
    min: {
        type: Number,
        default: undefined,
        required: false,
    },
    max: {
        type: Number,
        default: undefined,
        required: false,
    },
    step: {
        type: Number,
        default: undefined,
        required: false,
    },
    noHelp: {
        type: Boolean,
        default: false,
        required: false,
    },
    formId: {
        type: String,
        default: "form",
        required: false,
    },
    autocomplete: {
        type: [Array<string>, Function],
        default: () => {
            [];
        },
        required: false,
    },
    allowUpload: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const slots = useSlots();

const form = inject(props.formId, props.form);
const control =
    typeof props.control === "object"
        ? props.control
        : form &&
          !isEmpty(form) &&
          typeof props.control === "string" &&
          props.control !== "" &&
          Object.prototype.hasOwnProperty.call(form.controls, props.control)
        ? form.controls[props.control]
        : null;

const label = ref(
    props.label != undefined
        ? props.label
        : typeof props.control == "string"
        ? toTitleCase(props.control)
        : "",
);
const value = ref(
    props.modelValue != undefined
        ? props.modelValue
        : control != null
        ? control.value
        : "",
);
const id = ref(
    props.id != undefined
        ? props.id
        : typeof props.control == "string" && props.control.length > 0
        ? props.control
        : generateRandomElementId(),
);
const feedbackInvalid = ref(props.feedbackInvalid);
const active = ref(value.value?.toString().length ?? 0 > 0);
const focused = ref(false);
const disabled = ref(props.disabled);

watch(
    () => value.value,
    (newValue) => {
        mediaUrl.value = value.value.url ?? "";
    },
);

if (props.modelValue != undefined) {
    watch(
        () => props.modelValue,
        (newValue) => {
            value.value = newValue;
        },
    );
}

watch(
    () => props.feedbackInvalid,
    (newValue) => {
        feedbackInvalid.value = newValue;
    },
);

watch(
    () => props.disabled,
    (newValue) => {
        disabled.value = newValue;
    },
);

if (typeof control === "object" && control !== null) {
    watch(
        () => control.validation.result.valid,
        (newValue) => {
            feedbackInvalid.value = newValue
                ? ""
                : control.validation.result.invalidMessages[0];
        },
        { deep: true },
    );

    watch(
        () => control.value,
        (newValue) => {
            value.value = newValue;
        },
        { deep: true },
    );
}

if (form) {
    watch(
        () => form.loading(),
        (newValue) => {
            disabled.value = newValue;
        },
    );
}

const mediaUrl = ref(value.value.url ?? "");

const handleFocus = () => {
    active.value = true;
    focused.value = true;
};

const handleBlur = async () => {
    active.value = value.value?.length ?? 0 > 0;
    focused.value = false;
    emits("blur");

    if (control) {
        await control.validate();
        control.isValid();
    }
};

const handleCheckbox = (event: Event) => {
    const target = event.target as HTMLInputElement;
    value.value = target.checked;
    emits("update:modelValue", target.checked);

    if (control) {
        control.value = target.checked;
        feedbackInvalid.value = "";
    }
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    value.value = target.value;
    emits("update:modelValue", target.value);

    if (control) {
        control.value = target.value;
        feedbackInvalid.value = "";
    }
};

const handleKeyup = (event: Event) => {
    emits("keyup", event);
};

const handleClear = () => {
    value.value = "";
    emits("update:modelValue", "");
};

const handleChange = (event) => {
    if (control) {
        control.value = event.target.files[0];
        feedbackInvalid.value = "";
    }
};

const handleMediaSelect = async () => {
    let result = await openDialog(SMDialogMedia, {
        allowUpload: props.allowUpload,
        accepts: props.accepts,
    });
    if (result) {
        const mediaResult = result as Media;
        mediaUrl.value = mediaResult.url;
        emits("update:modelValue", mediaResult);
        if (control) {
            control.value = mediaResult;
            feedbackInvalid.value = "";
        }
    }
};

const computedAutocompleteItems = computed(() => {
    let autocompleteList = [];

    if (props.autocomplete) {
        if (typeof props.autocomplete === "function") {
            autocompleteList = props.autocomplete(value.value);
        } else {
            autocompleteList = props.autocomplete.filter((str) =>
                str.includes(value.value),
            );
        }

        return autocompleteList.sort((a, b) => a.localeCompare(b));
    }

    return autocompleteList;
});

const handleAutocompleteClick = (item) => {
    value.value = item;
    emits("update:modelValue", item);
};
</script>

<style lang="scss">
.input-control-prepend {
    p {
        display: block;
        color: var(--base-color-text);
        background-color: var(--base-color-dark);
        border-width: 1px 0 1px 1px;
        border-style: solid;
        border-color: var(--base-color-darker);
        border-radius: 8px 0 0 8px;
        padding: 16px 16px 16px 16px;
    }

    .button {
        border-width: 1px 0 1px 1px;
        border-style: solid;
        border-color: var(--base-color-darker);
        border-radius: 8px 0 0 8px;
    }

    & + .control-item .input-control {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
}

.input-control-append {
    p {
        display: block;
        color: var(--base-color-text);
        background-color: var(--base-color-dark);
        border-width: 1px 1px 1px 0;
        border-style: solid;
        border-color: var(--base-color-darker);
        border-radius: 0 8px 8px 0;
        padding: 16px 16px 16px 16px;
    }

    .button {
        border-width: 1px 1px 1px 0;
        border-style: solid;
        border-color: var(--base-color-darker);
        height: 50px;
        border-radius: 0 8px 8px 0;
    }
}

.control-item {
    max-width: 100%;
    align-items: start;

    .control-label {
        position: absolute;
        display: block;
        transform-origin: top left;
        transform: translate(16px, 16px) scale(1);
        transition: all 0.1s ease-in-out;
        color: var(--base-color-darker);
        pointer-events: none;

        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .invalid-icon {
        position: absolute;
        display: none;
        right: 10px;
        top: 14px;
        color: var(--danger-color);
        font-size: 150%;
    }

    .clear-icon {
        position: absolute;
        right: 12px;
        top: 18px;
        background-color: var(--input-clear-icon-color);
        border-radius: 50%;
        font-size: 80%;
        padding: 1px 1px 1px 0px;

        &:hover {
            color: var(--input-clear-icon-color-hover);
        }
    }

    .input-control {
        display: block;
        width: 100%;
        padding: 20px 16px 10px 16px;
        border: 1px solid var(--base-color-darker);
        border-radius: 8px;
        background-color: var(--base-color-light);
        color: var(--base-color-text);

        &:disabled {
            background-color: hsl(0, 0%, 92%);
            cursor: not-allowed;
        }
    }

    .autocomplete-list {
        position: absolute;
        list-style-type: none;
        top: 100%;
        width: 100%;
        margin: 0;
        padding: 0;
        border: 1px solid var(--base-color-darker);
        background-color: var(--base-color-light);
        color: var(--primary-color);
        z-index: 1;
        max-height: 200px;
        overflow: scroll;
        scroll-behavior: smooth;
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }

        li {
            cursor: pointer;
            padding: 8px 16px;
            margin: 2px;

            &:hover {
                background-color: var(--base-color);
            }
        }
    }

    .static-input-control {
        width: 100%;
        padding: 22px 16px 8px 16px;
        border: 1px solid var(--base-color-darker);
        border-radius: 8px;
        background-color: var(--base-color);
        height: 52px;
        overflow: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }
    }

    .file-input-control {
        opacity: 0;
        width: 0.1px;
        height: 0.1px;
        position: absolute;
        margin-left: -9999px;
    }

    .file-input-control-value {
        width: 100%;
        padding: 22px 16px 8px 16px;
        border: 1px solid var(--base-color-darker);
        border-radius: 8px 0 0 8px;
        background-color: var(--base-color);
        height: 52px;

        overflow: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }
    }

    .file-input-control-button {
        border-width: 1px 1px 1px 0;
        border-style: solid;
        border-color: var(--base-color-darker);
        border-radius: 0 8px 8px 0;
        padding: 16px 30px;
        width: auto;
    }

    .control-label-select {
        transform: translate(16px, 6px) scale(0.7);
    }

    .select-dropdown-icon {
        position: absolute;
        top: 50%;
        right: 0;
        transform: translate(-50%, -50%);
        font-size: 110%;
    }

    .select-input-control {
        appearance: none;
        width: 100%;
        padding: 20px 16px 8px 14px;
        border: 1px solid var(--base-color-darker);
        border-radius: 8px;
        background-color: var(--base-color-light);
        height: 52px;
        color: var(--base-color-text);
    }

    .control-label-checkbox {
        position: relative;
        display: flex;
        align-items: center;
        padding: 16px 0 16px 32px;
        pointer-events: all;
        transform: none;
        color: var(--base-color-text);

        &.disabled {
            color: var(--base-color-darker);
            cursor: not-allowed;

            .checkbox-control-box {
                background-color: var(--base-color);
            }
        }
    }

    .checkbox-control {
        opacity: 0;
        width: 0;
        height: 0;

        &:checked + .checkbox-control-box {
            .checkbox-control-tick {
                display: block;
            }
        }
    }

    .checkbox-control-box {
        position: absolute;
        top: 14px;
        left: 0;
        width: 24px;
        height: 24px;
        border: 1px solid var(--base-color-darker);
        border-radius: 2px;
        background-color: var(--base-color-light);

        .checkbox-control-tick {
            position: absolute;
            display: none;
            border-right: 3px solid var(--base-color-text);
            border-bottom: 3px solid var(--base-color-text);
            top: 1px;
            left: 7px;
            width: 8px;
            height: 16px;
            transform: rotate(45deg);
        }
    }

    .media-input-control {
        width: 100%;
        text-align: center;

        img,
        ion-icon {
            display: block;
            margin: 48px auto 8px auto;
            border-radius: 8px;
            font-size: 800%;
            max-height: 300px;
        }
    }

    .control-label-range {
        transform: none !important;
    }

    .range-control {
        margin-top: 24px;
        width: 100%;
    }

    .range-control-value {
        margin-top: 22px;
        padding-left: 16px;
        font-size: 90%;
        font-weight: 600;
        width: 48px;
        text-align: right;
    }
}
</style>
