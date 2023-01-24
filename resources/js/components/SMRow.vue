<template>
    <div :class="['row', { 'flex-fill': fill }]">
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
defineProps({
    fill: {
        type: Boolean,
        default: false,
    },
});
</script>

<style lang="scss">
.row {
    display: flex;
    // margin: map-get($spacer, 2) auto;
    margin: 0 auto;
    align-items: top;
    width: 100%;
    max-width: 1200px;
}

@media screen and (max-width: 768px) {
    .row {
        flex-direction: column;
    }
}
</style>
