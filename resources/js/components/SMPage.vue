<template>
    <template v-if="loading">
        <div class="page-loading">
            <SMLoadingIcon large />
        </div>
    </template>
    <template v-else-if="pageError < 300">
        <slot></slot>
    </template>
    <template v-else>
        <SMContainer class="page-error">
            <div class="error-number" v-html="modifiedPageError"></div>
            <div class="error-content">
                <h2>Ooops!</h2>
                <p v-if="pageError == 403">This page is not for you to view!</p>
                <p v-else-if="pageError == 404">
                    The page you are looking for does not exist!
                </p>
                <p v-else>
                    We are working to fix that what was broken. Please try again
                    later!
                </p>
                <SMButton label="Go Back" @click="handleClick" />
            </div>
        </SMContainer>
    </template>
</template>

<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRouter } from "vue-router";
import { useApplicationStore } from "../store/ApplicationStore";
import { useUserStore } from "../store/UserStore";
import SMButton from "../components/SMButton.vue";
import SMLoadingIcon from "./SMLoadingIcon.vue";

const router = useRouter();
const applicationStore = useApplicationStore();

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
});

const pageError = ref(props.pageError);

watch(
    () => props.pageError,
    (newValue) => {
        pageError.value = newValue;
    }
);

/**
 * Handle user clicking back/home button
 */
const handleClick = () => {
    router.go(-1);
};

const modifiedPageError = computed(() => {
    const errorNumber = pageError.value.toString(); // Convert to string
    const middleDigit = errorNumber.charAt(1); // Get the middle digit

    if (pageError.value >= 300) {
        applicationStore.setDynamicTitle("Server Error");
    }

    if (middleDigit === "0") {
        return errorNumber.replace(
            middleDigit,
            '<img src="/img/sad-monster.png" />'
        ); // Replace with image
    } else {
        return errorNumber; // Use the entire number
    }
});

const userStore = useUserStore();
if (
    props.permission.length !== 0 &&
    userStore.permissions.includes(props.permission) == false &&
    pageError.value < 300
) {
    pageError.value = 403;
}
</script>

<style lang="scss">
.page-loading {
    display: flex;
    flex-grow: 1;
    justify-content: center;
    align-items: center;
}

.page-error {
    display: flex;
    flex-direction: column;

    .error-number {
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 30vw;
        font-weight: 600;
        color: var(--primary-color);

        img {
            height: 25vw;
            margin: 0 #{map-get($spacing, 2)} 0 #{map-get($spacing, 3)};
        }
    }

    .error-content {
        text-align: center;
        font-size: 120%;

        h2 {
            margin-top: 0;
            margin-bottom: #{map-get($spacing, 2)};
        }
    }
}
</style>
