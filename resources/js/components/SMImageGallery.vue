<template>
    <div class="image-gallery" ref="gallery">
        <div
            class="image-gallery-item"
            v-for="(image, index) in images"
            :key="index">
            <img
                :src="image as string"
                class="image-gallery-image"
                @click="showModal(index)" />
        </div>
    </div>
    <div
        v-if="showModalImage !== null"
        class="image-gallery-modal"
        @click="hideModal">
        <img
            :src="images[showModalImage] as string"
            class="image-gallery-modal-image" />
        <div class="image-gallery-modal-close" @click="hideModal">&times;</div>
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
});

const gallery = ref(null);
const showModalImage = ref(null);

const showModal = (index) => {
    showModalImage.value = index;
    document.addEventListener("keydown", handleKeyDown);
};

const hideModal = () => {
    showModalImage.value = null;
    document.removeEventListener("keydown", handleKeyDown);
};

const handleKeyDown = (event) => {
    if (event.key === "ArrowLeft") {
        if (showModalImage.value !== null) {
            if (showModalImage.value > 0) {
                showModalImage.value--;
            }
        }
    } else if (event.key === "ArrowRight") {
        if (showModalImage.value !== null) {
            if (showModalImage.value < props.images.length - 1) {
                showModalImage.value++;
            }
        }
    } else if (event.key === "Escape") {
        hideModal();
    }
};

onMounted(() => {
    document.addEventListener("keydown", handleKeyDown);
});

onBeforeUnmount(() => {
    document.removeEventListener("keydown", handleKeyDown);
});
</script>

<style lang="scss">
.image-gallery {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;

    .image-gallery-image {
        cursor: pointer;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
}

@media (min-width: 768px) {
    .image-gallery {
        grid-template-columns: 1fr 1fr 1fr;
    }
}

@media (min-width: 1024px) {
    .image-gallery {
        grid-template-columns: 1fr 1fr 1fr 1fr;
    }
}

.image-gallery-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 5000;

    .image-gallery-modal-image {
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
    }

    .image-gallery-modal-close {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 30px;
        height: 30px;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 150%;
        font-weight: bold;
        color: white;
        cursor: pointer;
        transition: color 0.3s ease-in-out;

        &:hover {
            color: rgba(255, 255, 255, 0.7);
        }
    }
}
</style>
