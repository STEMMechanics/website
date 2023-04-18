<template>
    <div class="masthead">
        <SMContainer class="flex-column">
            <div class="main">
                <h1 class="title">{{ title }}</h1>
                <router-link
                    class="back"
                    v-if="props.backLink !== null"
                    :to="props.backLink"
                    ><ion-icon name="chevron-back-outline"></ion-icon>
                    {{ props.backTitle }}</router-link
                >
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

const tabGroups = [
    [
        { title: "Contact", to: "/contact" },
        { title: "Code of Conduct", to: "/code-of-conduct" },
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

        .back,
        .back:visited {
            display: flex;
            color: rgb(255, 255, 255, 0.75);
            margin-top: -24px;
            margin-bottom: 32px;
            font-size: 80%;
            text-decoration: none;
            transition: color 0.1s linear;

            &:hover {
                color: rgb(255, 255, 255, 1);

                ion-icon {
                    margin-left: -4px;
                    margin-right: 8px;
                }
            }

            ion-icon {
                margin-right: 4px;
                transition: margin 0.1s linear;
            }
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
