<template>
    <div class="sm-dropdown flex flex-col flex-1">
        <div
            :class="[
                'relative',
                'w-full',
                'flex',
                { 'input-active': active || focused },
            ]">
            <label
                :for="id"
                class="absolute select-none pointer-events-none transform-origin-top-left text-gray block translate-x-4 top-2 scale-70 transition"
                >{{ label }}</label
            >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="absolute right-1 top-1 h-10 pointer-events-none">
                <path d="M480-360 280-559h400L480-360Z" fill="currentColor" />
            </svg>
            <select
                :class="[
                    'appearance-none',
                    'border-1',
                    'border-gray',
                    'rounded-2',
                    'text-gray-6',
                    'text-lg',
                    'px-4',
                    'pt-5',
                    'flex-1',
                    { 'bg-gray-1': disabled },
                ]"
                v-bind="{
                    id: id,
                }"
                @focus="handleFocus"
                @blur="handleBlur"
                @input="handleInput"
                :value="value"
                :disabled="disabled">
                <option
                    v-for="option in Object.entries(props.options)"
                    :key="option[0]"
                    :value="option[0]"
                    :selected="option[0] == value">
                    {{ option[1] }}
                </option>
            </select>
        </div>
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
const active = ref(value.value?.toString().length ?? 0 > 0);
const focused = ref(false);
const disabled = ref(props.disabled);

watch(
    () => value.value,
    (newValue) => {
        active.value = newValue.toString().length > 0 || focused.value == true;
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
    () => props.disabled,
    (newValue) => {
        disabled.value = newValue;
    }
);

if (typeof control === "object" && control !== null) {
    watch(
        () => control.value,
        (newValue) => {
            value.value = newValue;
        },
        { deep: true }
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
    }
};
</script>

<style lang="scss">
.sm-dropdown {
    select {
        // appearance: none;
        // width: 100%;
        // padding: 20px 16px 8px 14px;
        // border: 1px solid var(--base-color-darker);
        // border-radius: 8px;
        // background-color: var(--base-color-light);
        // height: 52px;
        // color: var(--base-color-text);
    }
}

// label {
//     --un-translate-y: 0.85rem;
// }
// .input-active label {
//     transform: translate(16px, 6px) scale(0.7);
// }
</style>
