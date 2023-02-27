<template>
    <div
        :class="['sm-page-outer', { 'sm-no-breadcrumbs': noBreadcrumbs }]"
        :style="styleObject">
        <SMBreadcrumbs v-if="!noBreadcrumbs" />
        <SMLoader :loading="loading">
            <SMErrorForbidden
                v-if="pageError == 403 || !hasPermission()"></SMErrorForbidden>
            <SMErrorInternal
                v-if="pageError >= 500 && hasPermission()"></SMErrorInternal>
            <SMErrorNotFound
                v-if="pageError == 404 && hasPermission()"></SMErrorNotFound>
            <div v-if="pageError < 300 && hasPermission()" class="sm-page">
                <slot></slot>
                <SMContainer v-if="slots.container"
                    ><slot name="container"></slot
                ></SMContainer>
            </div>
        </SMLoader>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";
import SMBreadcrumbs from "../components/SMBreadcrumbs.vue";
import { useUserStore } from "../store/UserStore";
import SMErrorForbidden from "./errors/Forbidden.vue";
import SMErrorInternal from "./errors/Internal.vue";
import SMErrorNotFound from "./errors/NotFound.vue";
import SMContainer from "./SMContainer.vue";
import SMLoader from "./SMLoader.vue";

const props = defineProps({
    pageError: {
        type: Number,
        default: 200,
        required: false,
    },
    permission: {
        type: String,
        default: "",
        required: false,
    },
    loading: {
        type: Boolean,
        default: false,
        required: false,
    },
    background: {
        type: String,
        default: "",
        required: false,
    },
    noBreadcrumbs: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const slots = useSlots();
const userStore = useUserStore();
let styleObject = {};

if (props.background != "") {
    styleObject["backgroundImage"] = `url('${props.background}')`;
}

/**
 * Return if the current user has the props.permission to view this page.
 *
 * @returns {boolean} If the user has the permission.
 */
const hasPermission = (): boolean => {
    return (
        props.permission.length == 0 ||
        userStore.permissions.includes(props.permission)
    );
};
</script>

<style lang="scss">
.sm-page-outer {
    display: flex;
    flex-direction: column;
    flex: 1;
    width: 100%;
    padding-bottom: calc(map-get($spacer, 5) * 2);
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;

    &.sm-no-breadcrumbs {
        margin-bottom: 0;
    }

    .sm-page {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
}
</style>
