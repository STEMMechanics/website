<template>
    <div
        :class="[
            'sm-input',
            'flex',
            'flex-col',
            'flex-1',
            { 'sm-input-small': small },
        ]">
        <div
            :class="[
                'relative',
                'w-full',
                'flex',
                { 'input-active': active || focused },
            ]">
            <label
                :for="id"
                :class="[
                    'absolute',
                    'select-none',
                    'pointer-events-none',
                    'transform-origin-top-left',
                    'text-gray',
                    'block',
                    'scale-100',
                    'transition',
                    small
                        ? ['translate-x-4', 'text-sm', '-top-1.5']
                        : ['translate-x-5', 'top-0.5'],
                ]"
                >{{ label }}</label
            >
            <template v-if="!props.textarea">
                <input
                    :type="props.type"
                    :class="[
                        'w-full',
                        'text-gray-6',
                        'flex-1',
                        small
                            ? ['text-sm', 'pt-3', 'px-3']
                            : ['text-lg', 'pt-5', 'px-4'],
                        feedbackInvalid ? 'border-red-6' : 'border-gray',
                        feedbackInvalid ? 'border-2' : 'border-1',
                        { 'bg-gray-1': disabled },
                        { 'rounded-l-2': !slots.prepend },
                        { 'rounded-r-2': !slots.append },
                    ]"
                    v-bind="{
                        id: id,
                        autofocus: props.autofocus,
                        autocomplete: props.type === 'email' ? 'email' : null,
                        spellcheck: props.type === 'email' ? false : null,
                        autocorrect: props.type === 'email' ? 'on' : null,
                        autocapitalize: props.type === 'email' ? 'off' : null,
                    }"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    @input="handleInput"
                    @keyup="handleKeyup"
                    :value="value"
                    :disabled="disabled" />
                <template v-if="slots.append"
                    ><slot name="append"></slot
                ></template>
            </template>
            <template v-else>
                <textarea
                    :class="[
                        'w-full',
                        'text-gray-6',
                        'flex-1',
                        small
                            ? ['text-sm', 'pt-3', 'px-3']
                            : ['text-lg', 'pt-5', 'px-4'],
                        feedbackInvalid ? 'border-red-6' : 'border-gray',
                        feedbackInvalid ? 'border-2' : 'border-1',
                        { 'bg-gray-1': disabled },
                        { 'rounded-l-2': !slots.prepend },
                        { 'rounded-r-2': !slots.append },
                    ]"
                    v-bind="{
                        id: id,
                        autofocus: props.autofocus,
                        autocomplete: props.type === 'email' ? 'email' : null,
                        spellcheck: props.type === 'email' ? false : null,
                        autocorrect: props.type === 'email' ? 'on' : null,
                        autocapitalize: props.type === 'email' ? 'off' : null,
                    }"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    @input="handleInput"
                    @keyup="handleKeyup"
                    :value="value"
                    :disabled="disabled"></textarea>
            </template>
        </div>
        <p v-if="feedbackInvalid" class="px-2 pt-2 text-xs text-red-6">
            {{ feedbackInvalid }}
        </p>
        <p v-if="slots.default" class="px-2 pt-2 text-xs text-gray-5">
            <slot></slot>
        </p>
    </div>
</template>

<script setup lang="ts">
import { watch, ref, useSlots, inject } from "vue";
import { isEmpty, generateRandomElementId } from "../helpers/utils";
import { toTitleCase } from "../helpers/string";

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
    options: {
        type: Object,
        default: null,
        required: false,
    },
    formId: {
        type: String,
        default: "form",
        required: false,
    },
    small: {
        type: Boolean,
        default: false,
        required: false,
    },
    textarea: {
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
        active.value = newValue.toString().length > 0 || focused.value == true;
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
</script>

<style lang="scss">
.sm-input {
    label {
        --un-translate-y: 0.85rem;
    }
    .input-active label {
        transform: translate(16px, 6px) scale(0.7);
    }
    &.sm-input-small .input-active label {
        transform: translate(12px, 7px) scale(0.7);
    }
}
</style>
