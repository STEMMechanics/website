<template>
    <div
        :class="[
            'sm-row',
            { 'row-break-lg': breakLarge },
            { 'flex-fill': fill },
        ]">
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
defineProps({
    fill: {
        type: Boolean,
        default: false,
        required: false,
    },
    breakLarge: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>

<style lang="scss">
.sm-row {
    display: flex;
    flex-direction: row;
    margin: 0 auto;
    align-items: top;
    width: 100%;
    max-width: 1200px;
}

@media screen and (max-width: 992px) {
    .sm-row.row-break-lg {
        flex-direction: column;
    }
}

@media screen and (max-width: 768px) {
    .sm-row {
        flex-direction: column;
    }
}
</style>
