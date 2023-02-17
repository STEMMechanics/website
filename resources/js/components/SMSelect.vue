<template>
    <div :class="['form-group', { 'has-error': error }]">
        <label v-if="label" :class="{ required: required }">{{ label }}</label>
        <select
            :value="value"
            @input="input"
            @blur="handleBlur"
            @keydown="handleBlur">
            <option
                v-for="(value, key) in options"
                :key="key"
                :value="key"
                :selected="value == value">
                {{ value }}
            </option>
        </select>
        <div class="form-group-error">{{ error }}</div>
        <div v-if="slots.default" class="form-group-info">
            <slot></slot>
        </div>
        <div v-if="help" class="form-group-help">
            <ion-icon v-if="helpIcon" name="information-circle-outline" />
            {{ help }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots, ref, computed, watch, inject } from "vue";
import { isEmpty } from "../helpers/utils";
import { toTitleCase } from "../helpers/string";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    options: {
        type: Object,
        default() {
            return {};
        },
    },
    label: {
        type: String,
        default: "",
    },
    required: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: "text",
    },
    error: {
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
    feedbackInvalid: {
        type: String,
        default: "",
    },
});

const emits = defineEmits(["update:modelValue", "blur"]);
const slots = useSlots();
const objForm = inject("form", props.form);
const objControl =
    !isEmpty(objForm) && props.control != "" ? objForm[props.control] : null;
const label = ref("");
const value = ref(props.modelValue);
const feedbackInvalid = ref("");

const input = (event) => {
    emits("update:modelValue", event.target.value);
};

const handleBlur = (event) => {
    if (event.keyCode == undefined || event.keyCode == 9) {
        emits("blur", event);
    }
};

watch(
    () => props.label,
    (newValue) => {
        label.value = newValue;
    }
);

const initialOptions = computed(() => {
    return props.options;
});

watch(initialOptions, () => {
    if (
        value.value.length > 0 &&
        value.value in Object.keys(props.options) == true
    ) {
        emits("update:modelValue", value);
    } else if (Object.keys(props.options).length > 0) {
        emits("update:modelValue", Object.keys(props.options)[0]);
    }
});

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
</script>
