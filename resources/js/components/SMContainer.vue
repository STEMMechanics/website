<template>
    <div :class="['container', { full: isFull }]">
        <SMLoader :loading="loading">
            <d-error-forbidden
                v-if="pageError == 403 || !hasPermission()"></d-error-forbidden>
            <d-error-internal
                v-if="pageError >= 500 && hasPermission()"></d-error-internal>
            <d-error-not-found v-if="pageError == 404 && hasPermission()"
                >XX</d-error-not-found
            >
            <slot v-if="pageError < 300 && hasPermission()"></slot>
            <div v-if="isFull" class="container-inner">
                <slot name="inner"></slot>
            </div>
        </SMLoader>
    </div>
</template>

<script setup lang="ts">
import SMLoader from "./SMLoader.vue";
import DErrorForbidden from "./errors/Forbidden.vue";
import DErrorInternal from "./errors/Internal.vue";
import DErrorNotFound from "./errors/NotFound.vue";
import { useUserStore } from "../store/UserStore";
import { computed } from "vue";

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
    full: {
        type: Boolean,
        default: false,
        required: false,
    },
});
const userStore = useUserStore();

const hasPermission = () => {
    return (
        props.permission.length == 0 ||
        userStore.permissions.includes(props.permission)
    );
};

const isFull = computed(() => {
    return props.pageError == 200 ? props.full : false;
});
</script>

<style lang="scss">
.container {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding-left: 1rem;
    padding-right: 1rem;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;

    &.full {
        padding-left: 0;
        padding-right: 0;
        max-width: 100%;

        .container-inner {
            padding-left: 1rem;
            padding-right: 1rem;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
    }
}
</style>
