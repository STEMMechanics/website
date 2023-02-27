<template>
    <div :class="['form-group', { 'has-error': error }]">
        <label v-if="label" :class="{ required: required }">{{ label }}</label>
        <datepicker
            v-model="date"
            text-input
            auto-apply
            :is-24="false"
            :month-change-on-scroll="false"
            :preview-format="computedFormat"
            :format="computedFormat"
            :placeholder="props.placeholder"
            :range="range"
            :enable-time-picker="computedEnableTime"
            @update:model-value="onUpdate"
            @blur="onBlur"
            @change="onChange" />
        <div class="form-group-error">{{ error }}</div>
        <div v-if="slots.default" class="form-group-info">
            <slot></slot>
        </div>
        <div v-if="help" class="form-group-help">
            <!-- <font-awesome-icon v-if="helpIcon" :icon="helpIcon" /> -->
            {{ help }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { watch, computed, useSlots, ref } from "vue";
import Datepicker from "@vuepic/vue-datepicker";
import { format } from "date-fns";

const props = defineProps({
    modelValue: {
        type: [String, Array],
        default: null,
    },
    label: {
        type: String,
        default: "",
    },
    placeholder: {
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
    range: {
        type: Boolean,
        default: false,
        required: false,
    },
    enableTime: {
        type: Boolean,
        default: true,
        required: false,
    },
});

const emits = defineEmits(["update:modelValue", "blur", "change"]);
const slots = useSlots();

const onUpdate = (modelData) => {
    let emitResult = null;

    if (Array.isArray(modelData) == false) {
        emitResult = format(modelData, "yyyy-MM-dd HH:mm:ss");
    } else {
        emitResult = modelData.map((item, index) => {
            if (index == 0) {
                item.setHours(0, 0, 0, 0);
            } else {
                item.setHours(23, 59, 59, 999);
            }

            return format(item, "yyyy-MM-dd HH:mm:ss");
        });
    }

    emits("update:modelValue", emitResult);
};

const onBlur = () => {
    emits("blur");
};

const onChange = () => {
    emits("change");
};

let date = ref("");

const initialContent = computed(() => {
    return props.modelValue;
});

const computedFormat = computed(() => {
    return props.enableTime == true && props.range == false
        ? "d/MM/yyyy h:mm aa"
        : "d/MM/yyyy";
});

const computedEnableTime = computed(() => {
    return props.enableTime == true && props.range == false;
});

watch(initialContent, (newContent) => {
    if (
        typeof date.value == "undefined" ||
        (typeof date.value == "string" && date.value.length == 0)
    ) {
        date.value = newContent === undefined ? "" : newContent;
    }
});
</script>
