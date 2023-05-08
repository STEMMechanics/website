<template>
    <div class="tab-group">
        <ul class="tab-header">
            <li
                v-for="tab in tabs"
                :key="tab.id"
                :class="['tab-item', { selected: selectedTab == tab.id }]"
                @click="selectedTab = tab.id">
                {{ tab.label }}
            </li>
        </ul>
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import { provide, ref, useSlots, watch } from "vue";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
        required: false,
    },
});

const emits = defineEmits(["tabChanged", "update:modelValue"]);
const slots = useSlots();

const tabs = ref(
    slots.default().map((tab) => {
        const { label, id } = tab.props;
        return {
            label,
            id,
        };
    })
);

const selectedTab = ref(
    props.modelValue.length == 0 ? tabs.value[0].id : props.modelValue
);

if (props.modelValue.length == 0) {
    emits("update:modelValue", selectedTab.value);
}

watch(
    () => selectedTab.value,
    (newValue) => {
        emits("tabChanged", newValue);
        emits("update:modelValue", newValue);
    }
);

watch(
    () => props.modelValue,
    (newValue) => {
        selectedTab.value = newValue;
    }
);

provide("selectedTab", selectedTab);
</script>

<style lang="scss">
.tab-group {
    margin-bottom: 32px;

    .tab-header {
        list-style-type: none;
        margin: 0;
        padding: 0;
        border-bottom: 1px solid var(--tab-color-border);
    }

    .tab-item {
        display: inline-block;
        padding: 8px 16px;
        border: 1px solid transparent;
        margin-bottom: -1px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        color: var(--primary-color);
        position: relative;

        &.selected {
            color: var(--tab-color-text);
            background-color: var(--tab-color);
            border-top: 1px solid var(--tab-color-border);
            border-left: 1px solid var(--tab-color-border);
            border-bottom: 1px solid var(--tab-color);
            border-right: 1px solid var(--tab-color-border);

            &::after {
                display: block;
                content: "";
                position: absolute;
                bottom: -2px;
                height: 4px;
                left: 0px;
                right: 0px;
                border-bottom: 3px solid var(--tab-color);
                pointer-events: none;
            }
        }

        &:hover:not(.selected) {
            color: var(--primary-color);
            background-color: var(--tab-color-hover);
            border-bottom: 1px solid var(--tab-color-border);
        }
    }
}
</style>
