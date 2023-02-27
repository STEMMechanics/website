<template>
    <div class="sm-tab-group">
        <ul class="sm-tab-header">
            <li
                v-for="label in tabLabels"
                :key="label"
                :class="['sm-tab-item', { selected: selectedLabel == label }]"
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
.sm-tab-group {
    margin-bottom: map-get($spacer, 4);

    .sm-tab-header {
        // border-bottom: 1px solid $border-color;
        list-style-type: none;
        margin: 0;
        padding: 0;
    }

    .sm-tab-item {
        display: inline-block;
        padding: map-get($spacer, 2) map-get($spacer, 3);
        border: 1px solid transparent;
        margin-bottom: -1px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        color: $primary-color;
        transition: 0.2s all ease-out;

        &.selected {
            color: $font-color;
            background-color: #fff;
            border-top: 1px solid $border-color;
            border-left: 1px solid $border-color;
            border-bottom: 1px solid #fff;
            border-right: 1px solid $border-color;
        }

        &:hover:not(.selected) {
            color: $primary-color-dark;
            border-top: 1px solid $border-color;
            border-left: 1px solid $border-color;
            border-bottom: 1px solid #fff;
            border-right: 1px solid $border-color;
        }
    }
}
</style>
