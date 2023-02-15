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
        ]"
        :type="buttonType"
        @click="handleClick">
        {{ label }}
        <ion-icon v-if="icon && dropdown == null" :icon="icon" />
        <ion-icon
            v-if="dropdown != null"
            name="caret-down-outline"
            @click="handleToggleDropdown" />
        <ul v-if="dropdown != null" v-show="showDropdown">
            <li
                v-for="(dropdownLabel, dropdownItem) in dropdown"
                :key="dropdownItem"
                @click="handleClickItem(dropdownItem)">
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

const showDropdown = ref(false);
const buttonType = props.type == "submit" ? "submit" : "button";
const classType = props.type == "submit" ? "primary" : props.type;

const emits = defineEmits(["click"]);
const handleClick = () => {
    showDropdown.value = false;
    emits("click", "");
};

const handleToggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const handleClickItem = (item: string) => {
    showDropdown.value = false;
    emits("click", item);
};
</script>

<style lang="scss">
.button {
    cursor: pointer;

    &.button-block {
        display: block;
        width: 100%;
    }

    ion-icon {
        height: 1.2rem;
        width: 1.2rem;
        vertical-align: middle;
        cursor: pointer;
    }
}

// New content here
.dropdown {
    position: relative;
}

ul {
    position: absolute;
    z-index: 1;
    top: 100%;
    left: 0;
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
}

li {
    padding: 12px 16px;
    cursor: pointer;
}

li:hover {
    background-color: #f1f1f1;
}
</style>
