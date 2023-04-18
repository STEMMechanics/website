<template>
    <div class="sm-toolbar">
        <div v-if="slots.left" class="sm-toolbar-column sm-toolbar-column-left">
            <slot name="left"></slot>
        </div>
        <div v-if="slots.default" class="sm-toolbar-column">
            <slot></slot>
        </div>
        <div
            v-if="slots.right"
            class="sm-toolbar-column sm-toolbar-column-right">
            <slot name="right"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

const slots = useSlots();
</script>

<style lang="scss">
.sm-toolbar {
    display: flex;
    justify-content: space-between;
    margin-bottom: map-get($spacer, 2);
    width: 100%;

    .sm-toolbar-column {
        display: flex;
        flex: 1;
        flex-direction: row;
        align-items: flex-start;

        &.sm-toolbar-column-left {
            justify-content: flex-start;
        }

        & > * {
            margin: 0 map-get($spacer, 1);

            &:first-child {
                margin-left: 0;
            }

            &:last-child {
                margin-right: 0;
            }
        }

        &.sm-toolbar-column-right {
            justify-content: flex-end;
        }
    }
}

@media screen and (max-width: 768px) {
    .sm-toolbar {
        .sm-toolbar-column {
            flex-direction: column;

            & > * {
                margin: 0;
            }
        }
    }
}
</style>
