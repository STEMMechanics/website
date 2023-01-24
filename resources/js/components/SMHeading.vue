<template>
    <div class="heading">
        <router-link
            v-if="back != ''"
            :to="{ name: back }"
            class="heading-back">
            <font-awesome-icon icon="fa-solid fa-arrow-left" />{{ backLabel }}
        </router-link>
        <router-link v-if="close != ''" :to="{ name: close }" class="close">
            <font-awesome-icon icon="fa-solid fa-close" />
        </router-link>
        <span v-if="closeBack" class="close" @click="handleBack">
            <font-awesome-icon icon="fa-solid fa-close" />
        </span>
        <h1>{{ heading }}</h1>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from "vue-router";

defineProps({
    heading: {
        type: String,
        default: "",
        required: true,
    },
    back: {
        type: String,
        default: "",
    },
    backLabel: {
        type: String,
        default: "Back",
    },
    close: {
        type: String,
        default: "",
    },
    closeBack: {
        type: Boolean,
        default: false,
    },
});

const router = useRouter();
const handleBack = () => {
    router.back();
};
</script>

<style lang="scss">
.heading {
    position: relative;

    .heading-back {
        position: absolute;
        padding-top: 2rem;
        font-size: 80%;

        svg {
            margin-right: 0.5rem;
        }
    }

    .close {
        right: -10px;
        top: -10px;
        position: absolute;
        font-size: 120%;
        color: $font-color;

        &:hover {
            color: $danger-color;
        }
    }
}

// @media screen and (max-width: 768px) {

@media only screen and (max-width: 640px) {
    .heading .close {
        right: 0;
        top: -20px;
    }
}
</style>
