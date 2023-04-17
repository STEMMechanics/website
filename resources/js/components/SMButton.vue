<template>
    <button
        v-if="isEmpty(to)"
        :disabled="disabled"
        :class="[
            'button',
            classType,
            props.size,
            { 'button-block': block },
            { 'dropdown-button': dropdown },
        ]"
        ref="buttonRef"
        :style="{ minWidth: minWidth }"
        :type="buttonType"
        @click="handleClick">
        <ion-icon
            v-if="dropdown != null"
            name="caret-down-outline"
            class="button-icon-dropdown"
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
        <span v-if="!loading">{{ props.label }}</span>
        <SMLoadingIcon v-else class="button-icon-loading" />
    </button>
    <a
        v-else-if="!isEmpty(to) && typeof to == 'string'"
        :href="to"
        :disabled="disabled"
        :class="['button', classType, props.size, { 'button-block': block }]"
        :type="buttonType">
        <span class="button-label">{{ label }}</span>
    </a>
    <router-link
        v-else-if="!isEmpty(to) && typeof to == 'object'"
        :to="to"
        :disabled="disabled"
        :class="['button', classType, props.size, { 'button-block': block }]">
        <ion-icon v-if="icon && iconLocation == 'before'" :icon="icon" />
        {{ label }}
        <ion-icon v-if="icon && iconLocation == 'after'" :icon="icon" />
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
    position: relative;
    display: inline-block;
    font-family: var(--header-font-family);
    font-weight: 800;
    padding: 16px 32px 16px 32px;
    border: 0;
    background-color: var(--base-color-light);
    text-decoration: none;
    box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);

    &:hover:not(:disabled) {
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.5);
        filter: brightness(115%);
        cursor: pointer;
    }

    &:hover:disabled {
        cursor: not-allowed;
    }

    &.medium {
        padding: 12px 24px;
        font-size: 80%;
    }

    &.small {
        padding: 8px 16px;
        font-size: 75%;
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
