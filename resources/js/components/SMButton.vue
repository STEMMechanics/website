<template>
    <button
        v-if="isEmpty(to)"
        :disabled="disabled"
        :class="[
            'button',
            classType,
            props.size,
            { 'button-block': block },
            { 'button-dropdown': dropdown },
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
    type: { type: String, default: "secondary", required: false },
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
            loading.value = newValue;
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
    font-family: var(--header-font-family);
    font-weight: 800;
    padding: 12px 32px 12px 32px;
    border: 0;
    background-color: var(--base-color-light);
    text-decoration: none;
    box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;

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
            border-color: #999;
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.3);

            li {
                margin: 0;
                padding: 16px 24px;
                background-color: #fff;

                &:hover {
                    background-color: var(--primary-color-hover);
                }
            }
        }
    }

    &:hover:not(:disabled) {
        cursor: pointer;
    }

    &:hover:disabled {
        cursor: not-allowed;
    }

    &:disabled,
    &.primary:disabled {
        background-color: var(--base-color-dark);
        box-shadow: none;
    }

    &.medium {
        padding: 12px 24px;
        font-size: 80%;
        font-weight: 600;
    }

    &.small {
        padding: 8px 16px;
        font-size: 75%;
        font-weight: 600;
    }

    &.light {
        background-color: #eee;
        color: #095589;
    }

    &.primary {
        background-color: var(--primary-color);
        color: var(--base-color);
    }
}
</style>
