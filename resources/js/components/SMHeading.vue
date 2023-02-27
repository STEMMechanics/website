<template>
    <div class="sm-heading">
        <router-link v-if="back != ''" :to="{ name: back }" class="sm-back">
            <ion-icon name="arrow-back-outline" />{{ backLabel }}
        </router-link>
        <router-link v-if="close != ''" :to="{ name: close }" class="sm-close">
            <ion-icon name="close-outline" />
        </router-link>
        <span v-if="closeBack" class="sm-close" @click="handleBack">
            <ion-icon name="close-outline" />
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
.sm-heading {
    position: relative;

    .sm-back {
        position: absolute;
        padding-top: 2rem;
        font-size: 80%;
    }

    .sm-close {
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

@media only screen and (max-width: 640px) {
    .sm-heading .sm-close {
        right: 0;
        top: -20px;
    }
}
</style>
