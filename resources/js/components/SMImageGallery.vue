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
        :class="[
            'image-gallery-modal',
            { 'image-gallery-modal-buttons': showButtons },
        ]"
        @click="hideModal"
        @mousemove="handleModalUpdateButtons"
        @mouseleave="handleModalUpdateButtons">
        <img
            :src="images[showModalImage] as string"
            class="image-gallery-modal-image" />
        <div
            class="image-gallery-modal-prev"
            @click.stop="handleModalPrevImage"></div>
        <div
            class="image-gallery-modal-next"
            @click.stop="handleModalNextImage"></div>
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
let showButtons = ref(false);
let mouseMoveTimeout = null;

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
        handleModalPrevImage();
    } else if (event.key === "ArrowRight") {
        handleModalNextImage();
    } else if (event.key === "Escape") {
        hideModal();
    }
};

const handleModalUpdateButtons = () => {
    if (mouseMoveTimeout !== null) {
        clearTimeout(mouseMoveTimeout);
        mouseMoveTimeout = null;
    }

    showButtons.value = true;
    mouseMoveTimeout = setTimeout(() => {
        showButtons.value = false;
        mouseMoveTimeout = null;
    }, 3000);
};

const handleModalPrevImage = () => {
    handleModalUpdateButtons();

    if (showModalImage.value !== null) {
        if (showModalImage.value > 0) {
            showModalImage.value--;
        } else {
            showModalImage.value = props.images.length - 1;
        }
    }
};

const handleModalNextImage = () => {
    handleModalUpdateButtons();

    if (showModalImage.value !== null) {
        if (showModalImage.value < props.images.length - 1) {
            showModalImage.value++;
        } else {
            showModalImage.value = 0;
        }
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

    &.image-gallery-modal-buttons {
        .image-gallery-modal-prev,
        .image-gallery-modal-next {
            opacity: 1;
        }
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

    .image-gallery-modal-prev,
    .image-gallery-modal-next {
        position: absolute;
        display: flex;
        content: "";
        justify-content: center;
        align-items: center;
        top: 0;
        bottom: 0;
        width: 75px;
        background-color: rgba(0, 0, 0, 0.25);
        opacity: 0;
        transition: all 0.2s ease;
        cursor: pointer;

        &::before {
            position: absolute;
            display: block;
            content: "";
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #999;
            transition: all 0.2s ease;
        }

        &::after {
            position: absolute;
            display: block;
            content: "";
            width: 12px;
            height: 12px;
            transition: all 0.2s ease;
        }

        &:hover {
            &::before {
                background-color: #ddd;
            }
        }
    }

    .image-gallery-modal-prev {
        left: 0;

        &::after {
            border-left: 2px solid black;
            border-bottom: 2px solid black;
            transform: rotateZ(45deg) translateX(2px) translateY(-2px);
        }

        &:hover {
            &::before {
                transform: translateX(-3px);
            }

            &::after {
                transform: rotateZ(45deg) translateX(-0.5px) translateY(0.5px);
            }
        }
    }

    .image-gallery-modal-next {
        right: 0;

        &::after {
            border-right: 2px solid black;
            border-top: 2px solid black;
            transform: rotateZ(45deg) translateX(-2px) translateY(2px);
        }

        &:hover {
            &::before {
                transform: translateX(3px);
            }

            &::after {
                transform: rotateZ(45deg) translateX(0.5px) translateY(-0.5px);
            }
        }
    }
}
</style>
