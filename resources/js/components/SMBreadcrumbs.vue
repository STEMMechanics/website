<template>
    <SMContainer
        v-if="showBreadcrumbs"
        :class="[
            'flex-0',
            'breadcrumbs-outer',
            { closed: breadcrumbs.length == 0 },
        ]">
        <ul class="breadcrumbs">
            <li><router-link :to="{ name: 'home' }">Home</router-link></li>
            <li v-for="(val, idx) of breadcrumbs" :key="val.name">
                <router-link
                    v-if="idx != breadcrumbs.length - 1"
                    :to="{ name: val.name }"
                    >{{ val.meta?.title || val.name }}</router-link
                ><span v-else>{{ val.meta?.title || val.name }}</span>
            </li>
        </ul>
    </SMContainer>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { routes } from "../router";
import { useApplicationStore } from "../store/ApplicationStore";

const applicationStore = useApplicationStore();
const showBreadcrumbs = ref(true);

const breadcrumbs = computed(() => {
    const currentPageName = useRoute().name;

    if (currentPageName == "home") {
        return [];
    }

    const findMatch = (list) => {
        let found = null;
        let index = null;
        let child = null;

        list.every((entry) => {
            if (index == null && "path" in entry && entry.path == "") {
                index = entry;
            }

            if (child == null && "children" in entry) {
                child = findMatch(entry.children);
            }

            if (index != null && child != null) {
                child.unshift(index);
                found = child;
                return false;
            }

            if ("name" in entry && entry.name == currentPageName) {
                found = [entry];
                if (entry.path == "") {
                    return false;
                }
            }

            if (found != null && index != null) {
                found.unshift(index);
                return false;
            }

            return true;
        });

        return found || child;
    };

    let itemList = findMatch(routes);
    if (itemList) {
        if (applicationStore.dynamicTitle.length > 0) {
            let meta = [];

            if ("meta" in itemList) {
                meta = itemList[itemList.length - 1];
            }

            meta["title"] = applicationStore.dynamicTitle;

            itemList[itemList.length - 1]["meta"] = meta;
        }
    }

    return itemList || [];
});
</script>

<style lang="scss">
.breadcrumbs-outer.closed .breadcrumbs {
    opacity: 0;
    transition: opacity 0s;
    transition-delay: 0s;
}

.breadcrumbs {
    height: 3.25rem;
    display: flex;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    list-style-type: none;
    font-size: 75%;
    color: $secondary-color-dark;
    align-items: center;

    opacity: 1;
    transition: opacity 0.25s ease-in-out;
    transition-delay: 0.5s;

    li {
        display: flex;
        align-items: center;
        margin: 0;

        &:not(:last-child):after {
            display: inline-block;
            content: "";
            width: 4px;
            height: 4px;
            border-top: 2px solid #000;
            border-right: 2px solid #000;
            margin: 0 0.6rem;
            transform: rotate(45deg);
        }
    }
}
</style>
