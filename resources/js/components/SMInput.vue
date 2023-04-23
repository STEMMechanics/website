<template>
    <SMControl
        :class="['control-type-input', { 'input-active': active }]"
        :invalid="feedbackInvalid">
        <div v-if="slots.prepend" class="input-control-prepend">
            <slot name="prepend"></slot>
        </div>
        <div class="control-item">
            <template v-if="props.type == 'checkbox'">
                <label
                    class="control-label control-label-checkbox"
                    v-bind="{ for: id }"
                    ><input
                        :id="id"
                        type="checkbox"
                        class="checkbox-control"
                        :value="value"
                        @input="handleCheckbox" />
                    <span class="checkbox-control-box">
                        <span class="checkbox-control-tick"></span> </span
                    >{{ label }}</label
                >
            </template>
            <template v-else>
                <label class="control-label" v-bind="{ for: id }">{{
                    label
                }}</label>
                <template v-if="props.type == 'static'">
                    <div class="static-input-control" v-bind="{ id: id }">
                        <span class="text">
                            {{ value }}
                        </span>
                    </div>
                </template>
                <template v-else-if="props.type == 'file'">
                    <input
                        :id="id"
                        type="file"
                        class="file-input-control"
                        :accept="props.accept"
                        @change="handleChange" />
                    <div class="file-input-control-value">
                        {{ value?.name ? value.name : value }}
                    </div>
                    <label
                        class="button primary file-input-control-button"
                        :for="id"
                        >Select file</label
                    >
                </template>
                <template v-else-if="props.type == 'textarea'">
                    <ion-icon
                        class="invalid-icon"
                        name="alert-circle-outline"></ion-icon>
                    <textarea
                        :type="props.type"
                        class="input-control"
                        :disabled="disabled"
                        v-bind="{ id: id, autofocus: props.autofocus }"
                        v-model="value"
                        rows="5"
                        @focus="handleFocus"
                        @blur="handleBlur"
                        @input="handleInput"
                        @keyup="handleKeyup"></textarea>
                </template>
                <template v-else-if="props.type == 'select'">
                    <ion-icon
                        class="select-dropdown-icon"
                        name="caret-down-outline" />
                    <select class="select-input-control">
                        <option
                            v-for="option in Object.entries(props.options)"
                            :key="option[0]"
                            :value="option[0]">
                            {{ option[1] }}
                        </option>
                    </select>
                </template>
                <template v-else>
                    <ion-icon
                        class="invalid-icon"
                        name="alert-circle-outline"></ion-icon>
                    <ion-icon
                        v-if="
                            props.showClear &&
                            value?.length > 0 &&
                            !feedbackInvalid
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
                        @input="handleInput"
                        @keyup="handleKeyup" />
                </template>
            </template>
        </div>
        <div v-if="slots.append" class="input-control-append">
            <slot name="append"></slot>
        </div>
        <template v-if="slots.help" #help><slot name="help"></slot></template>
    </SMControl>
</template>

<script setup lang="ts">
import { inject, watch, ref, useSlots, computed } from "vue";
import { isEmpty, generateRandomElementId } from "../helpers/utils";
import { toTitleCase } from "../helpers/string";
import SMControl from "./SMControl.vue";
import { Booleanish } from "../helpers/api.types";

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
    accept: {
        type: String,
        default: "",
        required: false,
    },
    options: {
        type: Object,
        default: null,
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
        : typeof props.control == "string" && props.control.length > 0
        ? props.control
        : generateRandomElementId()
);
const feedbackInvalid = ref(props.feedbackInvalid);
const active = ref(value.value?.length ?? 0 > 0);
const focused = ref(false);
const disabled = ref(props.disabled);

watch(
    () => value.value,
    (newValue) => {
        active.value =
            newValue.length > 0 ||
            newValue instanceof File ||
            focused.value == true;
    }
);

if (props.modelValue != undefined) {
    watch(
        () => props.modelValue,
        (newValue) => {
            value.value = newValue;
        }
    );
}

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
    emits("blur");

    if (control) {
        await control.validate();
        control.isValid();
    }
};

const handleCheckbox = (event: Event) => {
    console.log("here");
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
</script>

<style lang="scss">
.control-group.control-type-input {
    .control-row {
        .control-item {
            align-items: start;
        }

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
                border-radius: 0 8px 8px 0;
            }
        }

        &:has(.control-item + .input-control-append)
            > .control-item
            .input-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .control-item {
            max-width: 100%;

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
                padding: 15px 30px;
                width: auto;
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
                padding: 22px 16px 8px 16px;
                border: 1px solid var(--base-color-darker);
                border-radius: 8px;
                background-color: var(--base-color-light);
                height: 52px;
            }

            .control-label-checkbox {
                position: relative;
                display: flex;
                align-items: center;
                padding: 16px 0 16px 32px;
                pointer-events: all;
                transform: none;
                color: var(--base-color-text);
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
        }
    }

    &.input-active {
        .control-item .control-label {
            transform: translate(16px, 6px) scale(0.7);
        }
    }

    &.control-invalid {
        .control-row .control-item {
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
