<template>
    <div
        :class="['column', { 'flex-fill': fill && width == '' }]"
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
.column {
    display: flex;
    margin: 0 12px;
    flex-direction: column;
}

.row .row .column {
    &:first-of-type {
        margin-left: 0;
    }

    &:last-of-type {
        margin-right: 0;
    }
}

@media screen and (max-width: 768px) {
    .column {
        flex-basis: auto !important;
        width: 100%;

        margin-left: 0;
        margin-right: 0;
    }
}

@media screen and (max-width: 640px) {
    .column {
        flex-direction: column;
    }
}
</style>
