<template>
    <div class="image">
        <SMLoading
            v-if="props.src != '' && imgLoaded == false && imgError == false" />
        <img
            v-if="props.src != '' && imgError == false"
            :src="src"
            @load="imgLoaded = true"
            @error="imgError = true" />
        <div v-if="imgError == true" class="image-error">
            <ion-icon name="alert-circle-outline"></ion-icon>
            <p>Error loading image</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import SMLoading from "./SMLoading.vue";

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
});

const imgLoaded = ref(false);
const imgError = ref(false);
</script>

<style lang="scss">
.image {
    display: flex;
    flex-basis: 300px;

    /* Firefox */
    justify-content: center;
    max-height: 300px;

    img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        border-radius: 8px;
    }

    .image-error {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 0 auto;

        ion-icon {
            font-size: 300%;
        }

        p {
            margin: 0;
        }
    }
}
</style>
