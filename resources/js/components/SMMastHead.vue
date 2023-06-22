<template>
    <div class="bg-sky-500 text-white">
        <div class="max-w-7xl mx-auto flex flex-col pt-10 px-4">
            <div class="pb-12">
                <h1 class="text-4xl">{{ title }}</h1>
                <router-link
                    class="sm-masthead-backlink text-sm"
                    v-if="props.backLink !== null"
                    :to="props.backLink"
                    ><svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 -960 960 960"
                        class="h-3">
                        <path
                            d="M400-80 0-480l400-400 56 57-343 343 343 343-56 57Z"
                            fill="currentColor" />
                    </svg>
                    {{ props.backTitle }}</router-link
                >
                <p
                    class="sm-masthead-info text-sm max-w-lg pt-2 text-sky-2"
                    v-if="slots.default">
                    <slot></slot>
                </p>
            </div>
            <div
                v-if="tabs().length > 0"
                class="block text-right overflow-x-auto whitespace-nowrap scroll-smooth scrollbar-width-none"
                style="scrollbar-width: none">
                <router-link
                    :to="tab.to"
                    v-for="(tab, idx) in tabs()"
                    :key="idx"
                    class="inline-block decoration-none !text-sky-1 px-6 py-4 font-bold hover:bg-sky-400 rounded-t-2"
                    exact-active-class="!bg-gray-1 !text-sky-500"
                    >{{ tab.title }}</router-link
                >
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";
import { useRoute } from "vue-router";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    backLink: {
        type: Object,
        default: () => {
            return null;
        },
        required: false,
    },
    backTitle: {
        type: String,
        default: "Back",
        required: false,
    },
});

const slots = useSlots();

const tabGroups = [
    [
        { title: "Contact", to: "/contact" },
        { title: "Code of Conduct", to: "/code-of-conduct" },
        { title: "Rules", to: "/rules" },
        { title: "Terms and Conditions", to: "/terms-and-conditions" },
        { title: "Privacy", to: "/privacy" },
    ],
    [
        { title: "Connect", to: "/minecraft" },
        { title: "Curve Calculator", to: "/minecraft/curve" },
    ],
];

const route = useRoute();

const tabs = () => {
    const currentTabGroup = tabGroups.find((items) =>
        items.some((item) => item.to === route.path)
    );

    return currentTabGroup || [];
};
</script>

<style lang="scss">
.sm-masthead-info a,
.sm-masthead-backlink {
    color: rgba(255, 255, 255, 1) !important;
    text-decoration: none;

    &:hover {
        color: rgba(255, 255, 255, 0.5) !important;
    }
}
</style>
