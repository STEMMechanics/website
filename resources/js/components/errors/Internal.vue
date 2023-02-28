<template>
    <SMPage no-breadcrumbs>
        <div class="sm-page-error sm-error-not-found">
            <div class="sm-error-number">
                5<img src="/img/sad-monster.png" />0
            </div>
            <div class="sm-error-content">
                <h2>Ooops!</h2>
                <p>
                    We are working to fix that what was broken. Please try again
                    later!
                </p>
                <SMButton label="Go Back" @click="handleClick" />
            </div>
        </div>
    </SMPage>
</template>

<script setup lang="ts">
import SMButton from "../SMButton.vue";
import { useRouter } from "vue-router";

const router = useRouter();

/**
 * Handle user clicking back/home button
 */
const handleClick = () => {
    router.go(-1);
};
</script>
