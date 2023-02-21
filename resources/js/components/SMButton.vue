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
        font-weight: normal;
        background: #fff !important;
        color: $primary-color !important;
        border-radius: 12px;
        border-width: 1px;
        font-size: 0.8rem;
        min-width: auto;

        span {
            flex: 1;
            border-right: 1px solid $primary-color;
            padding: 0;
            padding-top: calc(#{map-get($spacer, 1)} / 1.5);
            padding-bottom: calc(#{map-get($spacer, 1)} / 1.5);
            padding-left: map-get($spacer, 3);
            padding-right: map-get($spacer, 3);
        }

        ion-icon {
            height: 1rem;
            width: 1rem;
            padding: 0 map-get($spacer, 1) 0 map-get($spacer, 1);
        }

        &:hover {
            background-color: $primary-color !important;
            color: #fff !important;

            span {
                border-right: 1px solid $primary-color-light;
            }
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
        background-color: #f8f8f8;
        border: 1px solid $border-color;
        color: $primary-color;
        box-shadow: 0 0 14px rgba(0, 0, 0, 0.25);
    }

    li {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.1s ease-in-out;
    }

    li:hover {
        background-color: $primary-color;
        color: #f8f8f8;
    }
}
</style>
