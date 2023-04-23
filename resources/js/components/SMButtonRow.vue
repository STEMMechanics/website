<template>
    <div class="row button-row">
        <slot></slot>
        <div v-if="slots.right" class="button-row-right">
            <slot name="right"></slot>
        </div>
        <div v-if="slots.left" class="button-row-left">
            <slot name="left"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

const slots = useSlots();
</script>

<style lang="scss">
.button-row {
    flex-direction: row-reverse;
    padding: 0 16px;

    & > div {
        display: flex;
        flex: 1;
        width: 100%;
        gap: 15px;

        &.button-row-right {
            justify-content: flex-end;
        }
    }
}

@media only screen and (max-width: 768px) {
    .button-row {
        padding: 0;
        flex-direction: column-reverse;
        gap: 15px;

        & > div {
            flex-direction: column;
        }
    }
}
</style>
