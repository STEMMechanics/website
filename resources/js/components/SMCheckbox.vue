<template>
    <div class="sm-checkbox flex flex-col flex-1">
        <label :class="['control-label-checkbox', ,]" v-bind="{ for: id }"
            ><input
                :id="id"
                type="checkbox"
                class="opacity-0 w-0 h-0 select-none"
                :disabled="disabled"
                :checked="value"
                @input="handleCheckbox" />
            <span
                :class="[
                    'h-6',
                    'w-6',
                    'rounded',
                    'border-1',
                    'border-gray',
                    'absolute',
                    disabled ? 'bg-gray-2' : 'bg-white',
                ]">
                <span
                    :class="[
                        'sm-check',
                        'hidden',
                        'absolute',
                        'left-1.5',
                        'top-0.2',
                        'border-r-4',
                        'border-b-4',
                        'h-4',
                        'w-2.5',

                        'rotate-45',
                        disabled ? 'border-gray' : 'border-sky-5',
                    ]"></span> </span
            ><span
                :class="[
                    'pl-8',
                    'pt-0.5',
                    'inline-block',
                    disabled ? 'text-gray' : 'text-black',
                ]"
                >{{ label }}</span
            ></label
        >
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

const handleCheckbox = (event: Event) => {
    const target = event.target as HTMLInputElement;
    value.value = target.checked;
    emits("update:modelValue", target.checked);

    if (control) {
        control.value = target.checked;
        feedbackInvalid.value = "";
    }
};

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
</script>

<style lang="scss">
.sm-checkbox input:checked + span .sm-check {
    display: block;
}
</style>
