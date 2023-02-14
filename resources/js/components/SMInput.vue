<template>
    <div
        :class="[
            'sm-input-group',
            {
                'sm-input-active': inputActive,
                'sm-feedback-invalid': feedbackInvalid,
            },
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
                type == 'url'
            "
            :type="type"
            :placeholder="placeholder"
            :value="value"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown" />
        <textarea
            v-if="type == 'textarea'"
            rows="5"
            :value="value"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown"></textarea>
        <div v-if="type == 'file'" class="input-file-group">
            <input
                id="file"
                type="file"
                class="file"
                :accept="props.accept"
                @change="handleChange" />
            <label class="button" for="file">Select file</label>
            <div class="file-name">
                {{ modelValue?.name ? modelValue.name : modelValue }}
            </div>
        </div>
        <a v-if="type == 'link'" :href="href" target="_blank">{{
            props.modelValue
        }}</a>
        <span v-if="type == 'static'">{{ props.modelValue }}</span>
        <div v-if="slots.default || feedbackInvalid" class="sm-input-help">
            <span v-if="feedbackInvalid" class="sm-input-invalid">{{
                feedbackInvalid
            }}</span>
            <span v-if="slots.default" class="sm-input-info">
                <slot></slot>
            </span>
        </div>
        <div v-if="help" class="form-group-help">
            <ion-icon v-if="helpIcon" name="information-circle-outline" />
            {{ help }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { watch, computed, useSlots, ref, inject } from "vue";
import { toTitleCase } from "../helpers/string";
import { isEmpty } from "../helpers/utils";

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
    placeholder: {
        type: String,
        default: "",
        required: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: "text",
    },
    feedbackInvalid: {
        type: String,
        default: "",
    },
    help: {
        type: String,
        default: "",
    },
    helpIcon: {
        type: String,
        default: "",
    },
    accept: {
        type: String,
        default: "",
    },
    control: {
        type: String,
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

const objForm = inject("form", props.form);
const objControl =
    !isEmpty(objForm) && props.control != "" ? objForm[props.control] : null;

const label = ref("");
const feedbackInvalid = ref("");

watch(
    () => props.label,
    (newValue) => {
        label.value = newValue;
    }
);

const value = ref(props.modelValue);
if (objControl) {
    if (value.value.length > 0) {
        objControl.value = value.value;
    } else {
        value.value = objControl.value;
    }

    if (label.value.length == 0) {
        label.value = toTitleCase(props.control);
    }

    watch(
        () => objControl.validation.result.valid,
        (newValue) => {
            feedbackInvalid.value = newValue
                ? ""
                : objControl.validation.result.invalidMessages[0];
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

const inputActive = ref(value.value.length > 0);

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

const handleBlur = (event: Event) => {
    if (objControl) {
        objForm.validate(props.control);
        feedbackInvalid.value = objForm[props.control].validation.result.valid
            ? ""
            : objForm[props.control].validation.result.invalidMessages[0];
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

const inline = computed(() => {
    return ["static", "link"].includes(props.type);
});
</script>

<style lang="scss">
.sm-input-group {
    position: relative;
    display: flex;
    flex-direction: column;
    margin-bottom: map-get($spacer, 4);

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
        color: $font-color;
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

    .sm-input-help {
        font-size: 75%;
        margin: 0 map-get($spacer, 1);

        .sm-input-invalid {
            color: $danger-color;
            padding-right: map-get($spacer, 1);
        }
    }
}
</style>
