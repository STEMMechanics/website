<template>
    <button
        v-if="isEmpty(to)"
        :disabled="disabled"
        :class="[
            'button',
            classType,
            props.size,
            {
                'button-block': block,
                'button-dropdown': dropdown,
                'button-loading': loading,
            },
        ]"
        ref="buttonRef"
        :style="{ minWidth: minWidth }"
        :type="buttonType"
        @click.stop="handleClick">
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
        <template v-if="!loading">
            <span v-if="iconLocation.length > 0" class="button-label">
                <ion-icon
                    v-if="icon && iconLocation == 'before'"
                    :icon="icon" />
                {{ label }}
                <ion-icon v-if="icon && iconLocation == 'after'" :icon="icon" />
            </span>
            <span
                v-else-if="icon.length > 0"
                class="button-label button-label-icon">
                <ion-icon :icon="icon" />
            </span>
            <span v-else class="button-label">
                {{ label }}
            </span>
            <ion-icon
                v-if="dropdown != null"
                name="chevron-down-outline"
                class="button-icon-dropdown"
                @click.stop="handleClickToggleDropdown" />
        </template>
        <SMLoadingIcon v-else class="button-icon-loading" />
    </button>
    <a
        v-else-if="!isEmpty(to) && typeof to == 'string'"
        :href="to"
        :disabled="disabled"
        :class="['button', classType, props.size, { 'button-block': block }]"
        :type="buttonType">
        <span class="button-label">
            <ion-icon v-if="icon && iconLocation == 'before'" :icon="icon" />
            {{ label }}
            <ion-icon v-if="icon && iconLocation == 'after'" :icon="icon" />
        </span>
    </a>
    <router-link
        v-else-if="!isEmpty(to) && typeof to == 'object'"
        :to="to"
        :disabled="disabled"
        :class="['button', classType, props.size, { 'button-block': block }]">
        <span class="button-label">
            <ion-icon v-if="icon && iconLocation == 'before'" :icon="icon" />
            {{ label }}
            <ion-icon v-if="icon && iconLocation == 'after'" :icon="icon" />
        </span>
    </router-link>
</template>

<script setup lang="ts">
import { Ref, onMounted, ref, watch } from "vue";
import { isEmpty } from "../helpers/utils";
import SMLoadingIcon from "./SMLoadingIcon.vue";

const props = defineProps({
    label: { type: String, default: "Button", required: false },
    type: { type: String, default: "", required: false },
    icon: {
        type: String,
        default: "",
        required: false,
    },
    iconLocation: {
        type: String,
        default: "",
        required: false,
        validator: (value: string) => {
            return ["before", "after", ""].includes(value);
        },
    },
    to: {
        type: [String, Object],
        default: null,
        required: false,
        validator: (prop) =>
            typeof prop === "object" ||
            prop === null ||
            typeof prop === "string",
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
    size: {
        type: String,
        default: "",
        required: false,
    },
    dropdown: {
        type: Object,
        default: null,
        required: false,
        validator: (prop) => typeof prop === "object" || prop === null,
    },
    form: {
        type: Object,
        default: undefined,
        required: false,
    },
});

const buttonType: "submit" | "button" =
    props.type == "submit" ? "submit" : "button";
const classType = props.type == "submit" ? "primary" : props.type;
const disabled = ref(props.disabled);
const dropdownMenu: Ref<HTMLElement | null> = ref(null);
const loading = ref(false);
const minWidth = ref("");
const buttonRef = ref(null);

const emits = defineEmits(["click"]);

if (props.form !== undefined) {
    watch(
        () => props.form.loading(),
        (newValue) => {
            disabled.value = newValue;
            if (buttonType === "submit") {
                loading.value = newValue;
            }
        }
    );
}

if (props.disabled !== undefined) {
    watch(
        () => props.disabled,
        (newValue) => {
            disabled.value = newValue;
        }
    );
}

onMounted(() => {
    if (buttonRef.value) {
        minWidth.value = `${buttonRef.value.clientWidth}px`;
    }
});

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
.button {
    display: inline-block;
    font-family: var(--button-font-family);
    font-weight: var(--button-font-weight);
    padding: 12px 32px 12px 32px;
    border: 0;
    color: var(--button-color-text);
    background-color: var(--button-color);
    text-decoration: none;
    box-shadow: var(--base-shadow);
    text-align: center;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    white-space: nowrap;

    .button-label {
        display: inline-block;
        padding: 2px 0 3px 0;

        ion-icon {
            display: inline-block;
            width: 28px;
            height: 28px;
            margin: -8px 0;
        }

        &.button-label-icon {
            margin: 0 -32px;
        }
    }

    &.button-block {
        display: block;
    }

    &.button-dropdown {
        position: relative;

        .button-icon-dropdown {
            height: 18px;
            width: 18px;
            margin: -2px -18px -2px 8px;
        }

        &.medium .button-icon-dropdown {
            height: 16px;
            width: 16px;
            margin: -4px -18px -4px 8px;
        }

        ul {
            position: absolute;
            list-style-type: none;
            display: none;
            z-index: 100;
            top: 25%;
            right: 0;
            margin: 0;
            padding: 0;
            background-color: inherit;
            border-color: var(--button-dropdown-color-border);
            box-shadow: var(--base-shadow);

            li {
                margin: 0;
                padding: 16px 24px;
                background-color: var(--button-dropdown-color);

                &:hover {
                    background-color: var(--button-dropdown-color-hover);
                }
            }
        }
    }

    &:hover:not(:disabled):not(.disabled) {
        cursor: pointer;
    }

    &:hover:not(:disabled):not(.disabled):not(.button-dropdown) {
        filter: brightness(115%);
    }

    &:hover:disabled,
    &.disabled:hover {
        cursor: not-allowed;
    }

    &.disabled &:disabled,
    &.primary:disabled,
    &.primary.disabled {
        color: var(--button-disabled-color-text) !important;
        background-color: var(--button-disabled-color) !important;
        box-shadow: none;
    }

    &.medium {
        padding: 8px 24px;
        font-size: 80%;
        font-weight: 600;
    }

    &.small {
        padding: 4px 16px;
        font-size: 70%;
        font-weight: 600;
    }

    &.primary {
        background-color: var(--button-primary-color);
        color: var(--button-primary-color-text);
    }

    &.secondary {
        background-color: var(--button-secondary-color);
        color: var(--button-secondary-color-text);
    }

    &.danger {
        background-color: var(--button-danger-color);
        color: var(--button-danger-color-text);
    }
}

@media only screen and (max-width: 768px) {
    .button {
        display: block;
        width: 100%;
        text-align: center;
    }
}
</style>
