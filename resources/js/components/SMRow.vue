<template>
    <div
        :class="[
            'row',
            {
                'row-break-lg': breakLarge,
                'flex-fill': fill,
                'no-responsive': noResponsive,
            },
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
    noResponsive: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>

<style lang="scss">
.row {
    display: flex;
    flex-direction: row;
    margin: 8px auto;
    align-items: flex-start;
    width: 100%;
    max-width: 1200px;

    &.no-responsive {
        flex-direction: row !important;
    }
}

@media screen and (max-width: 992px) {
    .row.row-break-lg {
        flex-direction: column;
    }
}

@media screen and (max-width: 768px) {
    .row {
        flex-direction: column;
    }
}
</style>
