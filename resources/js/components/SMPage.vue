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
import { useUserStore } from "../store/UserStore";
import { useSlots } from "vue";
import SMLoader from "./SMLoader.vue";
import SMErrorForbidden from "./errors/Forbidden.vue";
import SMErrorInternal from "./errors/Internal.vue";
import SMErrorNotFound from "./errors/NotFound.vue";
import SMBreadcrumbs from "../components/SMBreadcrumbs.vue";
import SMContainer from "./SMContainer.vue";

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

const hasPermission = () => {
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

    &.sm-no-breadcrumbs {
        margin-bottom: 0;

        .sm-page {
            // padding-top: calc(map-get($spacer, 5) * 2);
            padding-bottom: calc(map-get($spacer, 5) * 2);
        }
    }

    .sm-page {
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
}
</style>
