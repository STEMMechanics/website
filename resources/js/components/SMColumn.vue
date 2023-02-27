<template>
    <div
        :class="['sm-column', { 'flex-fill': fill && width == '' }]"
        :style="styles">
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
const props = defineProps({
    fill: {
        type: Boolean,
        default: true,
    },
    width: {
        type: String,
        default: "",
    },
});

let styles = {};

if (props.width != "") {
    styles = {
        "flex-basis": props.width,
    };
}
</script>

<style lang="scss">
.sm-column {
    display: flex;
    margin: map-get($spacer, 2);
    flex-direction: column;
}

.sm-row .sm-row .sm-column {
    &:first-of-type {
        margin-left: 0;
    }

    &:last-of-type {
        margin-right: 0;
    }
}

@media screen and (max-width: 768px) {
    .sm-column {
        flex-basis: auto !important;
        width: 100%;

        margin-left: 0;
        margin-right: 0;
    }
}

@media screen and (max-width: 640px) {
    .sm-column {
        flex-direction: column;
    }
}
</style>
