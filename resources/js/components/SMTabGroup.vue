<template>
    <div class="tab-group">
        <ul class="tab-header">
            <li
                v-for="label in tabLabels"
                :key="label"
                :class="['tab-item', { selected: selectedLabel == label }]"
                @click="selectedLabel = label">
                {{ label }}
            </li>
        </ul>
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import { provide, ref, useSlots } from "vue";

const slots = useSlots();
const tabLabels = ref(slots.default().map((tab) => tab.props.label));
const selectedLabel = ref(tabLabels.value[0]);

provide("selectedLabel", selectedLabel);
</script>

<style lang="scss">
.tab-group {
    margin-bottom: 32px;

    .tab-header {
        list-style-type: none;
        margin: 0;
        padding: 0;
    }

    .tab-item {
        display: inline-block;
        padding: 8px 16px;
        border: 1px solid transparent;
        margin-bottom: -1px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        color: var(--primary-color);

        &.selected {
            color: var(--tab-color-text);
            background-color: var(--tab-color);
            border-top: 1px solid var(--tab-color-border);
            border-left: 1px solid var(--tab-color-border);
            border-bottom: 1px solid var(--tab-color);
            border-right: 1px solid var(--tab-color-border);
        }

        &:hover:not(.selected) {
            color: var(--primary-color);
            border-top: 1px solid var(--tab-color-border);
            border-left: 1px solid var(--tab-color-border);
            border-bottom: 1px solid var(--tab-color);
            border-right: 1px solid var(--tab-color-border);
        }
    }
}
</style>
