<template>
    <div class="masthead">
        <SMContainer class="flex-column">
            <div class="main">
                <h1 class="title">{{ title }}</h1>
            </div>
            <div v-if="tabs().length > 0" class="tabs">
                <router-link
                    :to="tab.to"
                    v-for="(tab, idx) in tabs()"
                    :key="idx"
                    class="tab-item"
                    active-class="active"
                    >{{ tab.title }}</router-link
                >
            </div>
        </SMContainer>
    </div>
</template>

<script setup lang="ts">
import { useRoute } from "vue-router";
import SMButton from "./SMButton.vue";
import SMInput from "./SMInput.vue";

defineProps({
    title: {
        type: String,
        required: true,
    },
});

const tabGroups = [
    [
        { title: "Contact", to: "/contact" },
        { title: "Code of Conduct", to: "/page" },
        { title: "Privacy", to: "/page" },
        { title: "Governance", to: "/page" },
        { title: "Teams", to: "/login" },
        { title: "License", to: "/page" },
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
.masthead {
    background-color: var(--primary-color);
    width: 100%;
    margin-bottom: 32px;

    .main {
        width: 100%;

        .title {
            color: rgb(255, 255, 255);
            text-align: left;
            margin-top: 32px;
            margin-bottom: 32px;
        }
    }

    .tabs {
        display: flex;
        justify-content: flex-end;
        width: 100%;

        .tab-item {
            color: rgba(255, 255, 255, 0.8);
            font-family: var(--header-font-family);
            font-weight: 800;
            font-size: 18px;
            text-decoration: none;
            padding: 16px 24px;

            &:hover {
                color: rgba(255, 255, 255);
                background-color: hsla(0, 0%, 100%, 0.1);
            }

            &.active {
                background-color: var(--base-color);
                color: var(--primary-color);
            }
        }
    }
}

@media (prefers-color-scheme: dark) {
    .masthead {
        background-color: var(--primary-color-light);

        .tabs .tab-item.active {
            color: var(--primary-color-light);
        }
    }
}
</style>
