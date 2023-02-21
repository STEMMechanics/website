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
        <ion-icon v-if="icon" :icon="icon" />
    </a>
    <button
        v-else-if="to == null"
        :disabled="disabled"
        :class="[
            'button',
            'prevent-select',
            classType,
            { 'button-block': block },
            { 'dropdown-button': dropdown },
        ]"
        :type="buttonType"
        @click="handleClick">
        <span>{{ label }}</span>
        <ion-icon v-if="icon && dropdown == null" :icon="icon" />
        <ion-icon
            v-if="dropdown != null"
            name="caret-down-outline"
            @click.stop="handleToggleDropdown" />
        <ul
            v-if="dropdown != null"
            ref="dropdownMenu"
            @mouseleave="handleMouseLeave">
            <li
                v-for="(dropdownLabel, dropdownItem) in dropdown"
                :key="dropdownItem"
                @click.stop="handleClickItem(dropdownItem)">
                {{ dropdownLabel }}
            </li>
        </ul>
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
        <ion-icon v-if="icon" :icon="icon" />
    </router-link>
</template>

<script setup lang="ts">
import { ref } from "vue";

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
    dropdown: {
        type: Object,
        default: null,
        required: false,
        validator: (prop) => typeof prop === "object" || prop === null,
    },
});

const buttonType = props.type == "submit" ? "submit" : "button";
const classType = props.type == "submit" ? "primary" : props.type;
const dropdownMenu = ref(null);

const emits = defineEmits(["click"]);
const handleClick = () => {
    emits("click", "");
};

const handleToggleDropdown = () => {
    dropdownMenu.value.style.display = "block";
};

const handleMouseLeave = () => {
    dropdownMenu.value.style.display = "none";
};

const handleClickItem = (item: string) => {
    emits("click", item);
};
</script>

<style lang="scss">
.button {
    cursor: pointer;
    position: relative;

    &.button-block {
        display: block;
        width: 100%;
    }

    &.dropdown-button {
        padding: 0;
        white-space: nowrap;
        display: flex;
        align-items: center;

        span {
            flex: 1;
            border-right: 1px solid rgba(255, 255, 255, 0.5);
            padding-top: map-get($spacer, 1);
            padding-bottom: map-get($spacer, 1);
            padding-left: map-get($spacer, 2);
        }

        ion-icon {
            padding: 0 calc(#{map-get($spacer, 1)} / 2);
        }
    }

    ion-icon {
        height: 1.2rem;
        width: 1.2rem;
        vertical-align: middle;
        cursor: pointer;
    }

    ul {
        position: absolute;
        display: none;
        z-index: 100;
        top: 20%;
        right: 0;
        min-width: 100%;
        list-style: none;
        padding: 0;
        margin: 0;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        color: $primary-color;
        box-shadow: 0 0 14px rgba(0, 0, 0, 0.25);
    }

    li {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.1s ease-in-out;
    }

    li:hover {
        background-color: #ddd;
    }
}
</style>
