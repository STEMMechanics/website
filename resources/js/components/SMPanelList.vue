<template>
    <div class="sm-panel-list">
        <div v-if="loading" class="sm-panel-list-loading">
            <SMLoadingIcon />
        </div>
        <div v-else-if="notFound" class="sm-panel-list-not-found">
            <ion-icon name="alert-circle-outline" />
            <p>{{ notFoundText }}</p>
        </div>
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import SMLoadingIcon from "./SMLoadingIcon.vue";

defineProps({
    loading: {
        type: Boolean,
        default: false,
        required: false,
    },
    notFound: {
        type: Boolean,
        default: false,
        required: false,
    },
    notFoundText: {
        type: String,
        default: "No items found",
        required: false,
    },
});
</script>

<style lang="scss">
.sm-panel-list {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1rem;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;

    .sm-panel-list-loading {
        display: flex;
        flex: 1;
        justify-content: center;
    }

    .sm-panel-list-not-found {
        display: flex;
        flex-direction: column;
        flex: 1;
        justify-content: center;
        align-items: center;

        ion-icon {
            font-size: 300%;
        }

        p {
            text-align: center;
            font-size: 130%;
            margin-top: 1rem;
        }
    }
}
</style>
