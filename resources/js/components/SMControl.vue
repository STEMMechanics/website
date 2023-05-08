<template>
    <div :class="['control-group', { 'control-invalid': invalid.length > 0 }]">
        <div class="control-row">
            <slot></slot>
        </div>
        <div v-if="!props.noHelp" class="control-help">
            <span v-if="invalid" class="control-feedback">
                {{ invalid }}
            </span>
            <span v-if="slots.help"><slot name="help"></slot></span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { watch, ref, useSlots } from "vue";

const props = defineProps({
    invalid: {
        type: String,
        default: "",
        required: false,
    },
    noHelp: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const slots = useSlots();
const invalid = ref(props.invalid);

watch(
    () => props.invalid,
    (newValue) => {
        invalid.value = newValue;
    }
);
</script>

<style lang="scss">
.control-group {
    width: 100%;

    .control-row {
        display: flex;
        align-items: center;

        .control-item {
            display: flex;
            flex: 1;
            position: relative;
            align-items: center;
        }
    }

    .control-help {
        display: block;
        font-size: 70%;
        min-height: 32px;
        padding-top: 8px;

        .control-feedback {
            color: var(--danger-color);
        }

        span + span:before {
            content: "-";
            margin: 0 6px;
        }
    }
}
</style>
