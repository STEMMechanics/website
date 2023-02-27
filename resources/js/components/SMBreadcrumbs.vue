<template>
    <SMContainer
        :class="[
            'flex-0',
            'sm-breadcrumbs-container',
            { closed: computedRouteCrumbs.length == 0 },
        ]">
        <ul class="sm-breadcrumbs">
            <li><router-link :to="{ name: 'home' }">Home</router-link></li>
            <li
                v-for="(routeItem, index) of computedRouteCrumbs"
                :key="routeItem.name">
                <router-link
                    v-if="index != computedRouteCrumbs.length - 1"
                    :to="{ name: routeItem.name }"
                    >{{ routeItem.meta?.title || routeItem.name }}</router-link
                ><span v-else>{{
                    routeItem.meta?.title || routeItem.name
                }}</span>
            </li>
        </ul>
    </SMContainer>
</template>

<script setup lang="ts">
import { computed, ComputedRef } from "vue";
import { RouteRecordRaw, useRoute } from "vue-router";
import { routes } from "../router";

/**
 * Return a list of routes from the current page back to the root
 */
const computedRouteCrumbs: ComputedRef<RouteRecordRaw[]> = computed(() => {
    const currentPageName = useRoute().name;

    if (currentPageName == "home") {
        return [];
    }

    const findMatch = (list: RouteRecordRaw[]): RouteRecordRaw[] | null => {
        let found: RouteRecordRaw[] | null = null;
        let index: RouteRecordRaw | null = null;
        let child: RouteRecordRaw[] | null = null;

        list.every((entry: RouteRecordRaw) => {
            if (index == null && "path" in entry && entry.path == "") {
                index = entry;
            }

            if (child == null && entry.children) {
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
    return itemList || [];
});
</script>

<style lang="scss">
.sm-breadcrumbs-container.closed .sm-breadcrumbs {
    opacity: 0;
    transition: opacity 0s;
    transition-delay: 0s;
}

.sm-breadcrumbs {
    height: 3.25rem;
    display: flex;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 0 1rem 0 0;
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
        overflow: hidden;

        span {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }

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
