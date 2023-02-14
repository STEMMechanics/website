<template>
    <div :class="['form-group', { 'has-error': error }]">
        <label v-if="label" :class="{ required: required }">{{ label }}</label>
        <select
            :value="modelValue"
            @input="input"
            @blur="handleBlur"
            @keydown="handleBlur">
            <option
                v-for="(value, key) in options"
                :key="key"
                :value="key"
                :selected="modelValue == value">
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
import { useSlots, onMounted, computed, watch } from "vue";

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
});

const emits = defineEmits(["update:modelValue", "blur"]);
const slots = useSlots();

const input = (event) => {
    emits("update:modelValue", event.target.value);
};

const handleBlur = (event) => {
    if (event.keyCode == undefined || event.keyCode == 9) {
        emits("blur", event);
    }
};

const initialOptions = computed(() => {
    return props.options;
});

watch(initialOptions, () => {
    if (
        props.modelValue.length > 0 &&
        props.modelValue in Object.keys(props.options) == true
    ) {
        emits("update:modelValue", props.modelValue);
    } else if (Object.keys(props.options).length > 0) {
        emits("update:modelValue", Object.keys(props.options)[0]);
    }
});
</script>
