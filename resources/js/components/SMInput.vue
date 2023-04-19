<template>
    <div
        :class="[
            'input-control-group',
            { 'input-active': active, 'input-invalid': feedbackInvalid },
        ]">
        <div class="input-control-row">
            <div v-if="slots.prepend" class="input-control-prepend">
                <slot name="prepend"></slot>
            </div>
            <div class="input-control-item">
                <label class="input-label" v-bind="{ for: id }">{{
                    label
                }}</label>
                <ion-icon
                    class="invalid-icon"
                    name="alert-circle-outline"></ion-icon>
                <ion-icon
                    v-if="
                        props.showClear && value?.length > 0 && !feedbackInvalid
                    "
                    class="clear-icon"
                    name="close-outline"
                    @click.stop="handleClear"></ion-icon>
                <input
                    :type="props.type"
                    class="input-control"
                    :disabled="disabled"
                    v-bind="{ id: id, autofocus: props.autofocus }"
                    v-model="value"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    @input="handleInput" />
            </div>
            <div v-if="slots.append" class="input-control-append">
                <slot name="append"></slot>
            </div>
        </div>
        <div v-if="slots.default || feedbackInvalid" class="input-help">
            <span v-if="feedbackInvalid" class="input-invalid">
                {{ feedbackInvalid }}
            </span>
            <span v-if="slots.default"><slot></slot></span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { inject, watch, ref, useSlots } from "vue";
import { isEmpty } from "../helpers/utils";
import { toTitleCase } from "../helpers/string";

const emits = defineEmits(["update:modelValue", "change"]);
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
        type: String,
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
});

const slots = useSlots();

const form = inject("form", props.form);
const control =
    typeof props.control === "object"
        ? props.control
        : !isEmpty(form) &&
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
        : ""
);
const value = ref(
    props.modelValue != undefined
        ? props.modelValue
        : control != null
        ? control.value
        : ""
);
const id = ref(
    props.id != undefined
        ? props.id
        : typeof props.control == "string"
        ? props.control
        : ""
);
const feedbackInvalid = ref(props.feedbackInvalid);
const active = ref(value.value?.length ?? 0 > 0);
const focused = ref(false);
const disabled = ref(props.disabled);

watch(
    () => value.value,
    (newValue) => {
        active.value = newValue.length > 0 || focused.value == true;
    }
);

watch(
    () => props.feedbackInvalid,
    (newValue) => {
        feedbackInvalid.value = newValue;
    }
);

if (typeof control === "object" && control !== null) {
    watch(
        () => control.validation.result.valid,
        (newValue) => {
            feedbackInvalid.value = newValue
                ? ""
                : control.validation.result.invalidMessages[0];
        },
        { deep: true }
    );

    watch(
        () => control.value,
        (newValue) => {
            value.value = newValue;
        },
        { deep: true }
    );
}

if (form) {
    watch(
        () => form.loading(),
        (newValue) => {
            disabled.value = newValue;
        }
    );
}

const handleFocus = () => {
    active.value = true;
    focused.value = true;
};

const handleBlur = async () => {
    active.value = value.value?.length ?? 0 > 0;
    focused.value = false;
    emits("change");

    if (control) {
        await control.validate();
        control.isValid();
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

const handleClear = () => {
    value.value = "";
    emits("update:modelValue", "");
    emits("change");
};
</script>

<style lang="scss">
.input-control-group {
    margin-bottom: 16px;
    width: 100%;

    .input-control-row {
        display: flex;
        align-items: center;
        margin-bottom: 8px;

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

            & + .input-control-item .input-control {
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
                border-radius: 0 8px 8px 0;
            }
        }

        &:has(.input-control-item + .input-control-append)
            > .input-control-item
            .input-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-control-item {
            flex: 1;
            position: relative;

            .input-label {
                position: absolute;
                display: block;
                transform-origin: top left;
                transform: translate(16px, 16px) scale(1);
                transition: all 0.1s ease-in-out;
                color: var(--base-color-darker);
                pointer-events: none;
            }

            .invalid-icon {
                position: absolute;
                display: none;
                right: 10px;
                top: 14px;
                color: var(--danger-color);
                font-size: 28px;
            }

            .clear-icon {
                position: absolute;
                right: 12px;
                top: 18px;
                background-color: #ccc;
                border-radius: 50%;
                font-size: 80%;
                padding: 1px 1px 1px 0px;

                &:hover {
                    color: #fff;
                }
            }

            .input-control {
                display: block;
                width: 100%;
                padding: 20px 16px 8px 16px;
                border: 1px solid var(--base-color-darker);
                border-radius: 8px;
                background-color: var(--base-color-light);
                color: var(--base-color-text);

                &:disabled {
                    background-color: hsl(0, 0%, 92%);
                    cursor: not-allowed;
                }
            }
        }
    }

    .input-help {
        display: block;
        font-size: 70%;
        margin-bottom: 8px;

        .input-invalid {
            color: var(--danger-color);
        }

        span + span:before {
            content: "-";
            margin: 0 6px;
        }
    }

    &.input-active {
        .input-control-item .input-label {
            transform: translate(16px, 6px) scale(0.8);
        }
    }

    &.input-invalid {
        .input-control-row .input-control-item {
            .invalid-icon {
                display: block;
            }

            .input-control {
                border: 2px solid var(--danger-color);
            }
        }
    }
}
</style>
