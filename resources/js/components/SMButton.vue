<template>
    <button
        v-if="isEmpty(to)"
        :disabled="disabled"
        :class="[
            'sm-button',
            classType,
            { 'sm-button-small': small },
            { 'sm-button-block': block },
            { 'sm-dropdown-button': dropdown },
        ]"
        :type="buttonType"
        @click="handleClick">
        <ion-icon
            v-if="icon && dropdown == null && iconLocation == 'before'"
            :icon="icon"
            class="sm-button-icon-before" />
        <span>{{ label }}</span>
        <ion-icon
            v-if="icon && dropdown == null && iconLocation == 'after'"
            :icon="icon"
            class="sm-button-icon-after" />
        <ion-icon
            v-if="dropdown != null"
            name="caret-down-outline"
            class="sm-button-icon-dropdown"
            @click.stop="handleClickToggleDropdown" />
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
    <a
        v-else-if="!isEmpty(to) && typeof to == 'string'"
        :href="to"
        :disabled="disabled"
        :class="[
            'sm-button',
            classType,
            { 'sm-button-small': small },
            { 'sm-button-block': block },
        ]"
        :type="buttonType">
        {{ label }}
        <ion-icon v-if="icon" :icon="icon" />
    </a>
    <router-link
        v-else-if="!isEmpty(to) && typeof to == 'object'"
        :to="to"
        :disabled="disabled"
        :class="[
            'sm-button',
            classType,
            { 'sm-button-small': small },
            { 'sm-button-block': block },
        ]">
        <ion-icon v-if="icon && iconLocation == 'before'" :icon="icon" />
        {{ label }}
        <ion-icon v-if="icon && iconLocation == 'after'" :icon="icon" />
    </router-link>
</template>

<script setup lang="ts">
import { Ref, ref } from "vue";
import { isEmpty } from "../helpers/utils";

const props = defineProps({
    label: { type: String, default: "Button", required: false },
    type: { type: String, default: "primary", required: false },
    icon: {
        type: String,
        default: "",
        required: false,
    },
    iconLocation: {
        type: String,
        default: "after",
        required: false,
        validator: (value: string) => {
            return ["before", "after"].includes(value);
        },
    },
    to: {
        type: [String, Object],
        default: null,
        required: false,
        validator: (prop) => typeof prop === "object" || prop === null,
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
    small: {
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

const buttonType: "submit" | "button" =
    props.type == "submit" ? "submit" : "button";
const classType = props.type == "submit" ? "primary" : props.type;
const dropdownMenu: Ref<HTMLElement | null> = ref(null);

const emits = defineEmits(["click"]);
const handleClick = () => {
    emits("click", "");
};

const handleClickToggleDropdown = () => {
    if (dropdownMenu.value) {
        dropdownMenu.value.style.display = "block";
    }
};

const handleMouseLeave = () => {
    if (dropdownMenu.value) {
        dropdownMenu.value.style.display = "none";
    }
};

const handleClickItem = (item: string) => {
    emits("click", item);
};
</script>

<style lang="scss">
a.sm-button,
a:visited.sm-button,
.sm-button {
    cursor: pointer;
    position: relative;
    padding: map-get($spacer, 2) map-get($spacer, 4);
    color: white;
    font-weight: 800;
    border-width: 2px;
    border-style: solid;
    border-radius: 24px;
    transition: background-color 0.1s, color 0.1s;
    background-color: $secondary-color;
    border-color: $secondary-color;
    min-width: 7rem;
    text-align: center;
    display: inline-block;

    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;

    &.sm-button-block {
        display: block;
        width: 100%;
    }

    &.sm-button-small {
        font-size: 85%;
        font-weight: normal;
        padding: map-get($spacer, 1) map-get($spacer, 3);
    }

    &.sm-dropdown-button {
        padding: 0;
        white-space: nowrap;
        display: flex;
        align-items: center;
        font-weight: normal;
        background: #fff !important;
        color: $primary-color !important;
        border-radius: 12px;
        border-width: 1px;
        font-size: 0.8rem;
        min-width: auto;

        span {
            flex: 1;
            border-right: 1px solid $primary-color-lighter;
            padding-top: calc(#{map-get($spacer, 1)} / 1.5);
            padding-bottom: calc(#{map-get($spacer, 1)} / 1.5);
            padding-left: map-get($spacer, 3);
            padding-right: map-get($spacer, 3);
        }

        .sm-button-icon-dropdown {
            height: 1rem;
            width: 1rem;
            padding: 0 0.3rem 0 0.2rem;
        }

        &:hover {
            background-color: $primary-color !important;
            color: #fff !important;

            span {
                border-right: 1px solid $primary-color-light;
            }
        }
    }

    &:disabled {
        cursor: not-allowed;
        background-color: $secondary-color !important;
        border-color: $secondary-color !important;
        opacity: 0.5;
    }

    &:hover:not(:disabled) {
        text-decoration: none;
        color: $secondary-color;
    }

    &.primary {
        background-color: $primary-color;
        border-color: $primary-color;

        &:hover:not(:disabled) {
            color: $primary-color;
        }
    }

    &.primary-outline {
        background-color: transparent;
        border-color: $primary-color;
        color: $primary-color;

        &:hover:not(:disabled) {
            color: $primary-color;
        }
    }

    &.secondary {
        background-color: $secondary-color;
        border-color: $secondary-color;

        &:hover:not(:disabled) {
            color: $secondary-color;
        }
    }

    &.secondary-outline {
        background-color: transparent;
        border-color: $secondary-color;
        color: $secondary-color;

        &:hover:not(:disabled) {
            color: $secondary-color;
        }
    }

    &.danger {
        background-color: $danger-color;
        border-color: $danger-color;

        &:hover:not(:disabled) {
            color: $danger-color;
        }
    }

    &.danger-outline {
        background-color: transparent;
        border-color: $danger-color;
        color: $danger-color;

        &:hover:not(:disabled) {
            color: $danger-color;
        }
    }

    &.outline {
        background-color: transparent;
        border-color: $outline-color;
        color: $outline-color;

        &:hover:not(:disabled) {
            background-color: $outline-color;
            border-color: $outline-color;
            color: $outline-hover-color;
        }
    }

    &:hover:not(:disabled) {
        background-color: #fff;
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
        background-color: #f8f8f8;
        border: 1px solid $border-color;
        border-radius: 8px;
        color: $primary-color;
        box-shadow: 0 0 14px rgba(0, 0, 0, 0.5);
    }

    li {
        padding: map-get($spacer, 1);
        font-size: 100%;
        cursor: pointer;
        transition: background-color 0.1s ease-in-out;

        &:first-child {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        &:last-child {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
    }

    li:hover {
        background-color: $primary-color;
        color: #f8f8f8;
    }

    .sm-button-icon-before {
        margin-right: map-get($spacer, 1);
    }

    .sm-button-icon-after {
        margin-left: map-get($spacer, 1);
    }
}
</style>
