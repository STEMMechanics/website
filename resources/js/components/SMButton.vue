<template>
    <a
        v-if="href.length > 0 || typeof to == 'string'"
        :href="href"
        :disabled="disabled"
        :class="[
            'button',
            'prevent-select',
            classType,
            { 'button-block': block },
        ]"
        :type="buttonType">
        {{ label }}
        <font-awesome-icon v-if="icon" :icon="icon" />
    </a>
    <button
        v-else-if="to == null"
        :disabled="disabled"
        :class="[
            'button',
            'prevent-select',
            classType,
            { 'button-block': block },
        ]"
        :type="buttonType">
        {{ label }}
        <font-awesome-icon v-if="icon" :icon="icon" />
    </button>
    <router-link
        v-else
        :to="to"
        :disabled="disabled"
        :class="[
            'button',
            'prevent-select',
            classType,
            { 'button-block': block },
        ]">
        {{ label }}
        <font-awesome-icon v-if="icon" :icon="icon" />
    </router-link>
</template>

<script setup lang="ts">
const props = defineProps({
    label: { type: String, default: "Button", required: false },
    type: { type: String, default: "primary", required: false },
    icon: {
        type: String,
        default: "",
        required: false,
    },
    to: {
        type: [String, Object],
        default: null,
        required: false,
        validator: (prop) => typeof prop === "object" || prop === null,
    },
    href: {
        type: String,
        default: "",
        required: false,
    },
    disabled: {
        type: Boolean,
        default: false,
        required: false,
    },
    block: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const buttonType = props.type == "submit" ? "submit" : "button";
const classType = props.type == "submit" ? "primary" : props.type;
</script>

<style lang="scss">
.button {
    &.button-block {
        display: block;
        width: 100%;
    }
}
</style>
