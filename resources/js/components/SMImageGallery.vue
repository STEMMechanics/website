<template>
    <div
        :class="[
            'flex',
            'gap-4',
            'my-4',
            'select-none',
            props.showEditor
                ? ['overflow-auto']
                : ['flex-wrap', 'flex-justify-center'],
        ]">
        <div
            v-for="(image, index) in modelValue"
            class="flex flex-col flex-justify-center relative sm-gallery-item p-1"
            :key="index">
            <img
                :src="mediaGetVariantUrl(image as Media, 'small')"
                class="max-h-40 max-w-40 cursor-pointer"
                @click="showGalleryModal(index)" />
            <div
                class="absolute rounded-5 bg-white -top-0.25 -right-0.25 hidden cursor-pointer item-delete"
                @click="handleRemoveItem(image.id)">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-6 w-6 block"
                    viewBox="0 0 24 24">
                    <path
                        d="M12,2C17.53,2 22,6.47 22,12C22,17.53 17.53,22 12,22C6.47,22 2,17.53 2,12C2,6.47 6.47,2 12,2M15.59,7L12,10.59L8.41,7L7,8.41L10.59,12L7,15.59L8.41,17L12,13.41L15.59,17L17,15.59L13.41,12L17,8.41L15.59,7Z"
                        fill="rgba(185,28,28,1)" />
                </svg>
            </div>
        </div>
        <div v-if="props.showEditor" class="flex flex-col flex-justify-center">
            <div
                class="flex flex-col flex-justify-center flex-items-center h-23 w-40 cursor-pointer bg-gray-300 text-gray-800 hover:text-gray-600"
                @click="handleAddToGallery">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-15 w-15"
                    viewBox="0 0 24 24">
                    <title>Add image</title>
                    <path
                        d="M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M13,7H11V11H7V13H11V17H13V13H17V11H13V7Z"
                        fill="currentColor" />
                </svg>
            </div>
        </div>
    </div>
    <div
        v-if="props.showEditor == false && showModalImage !== null"
        :class="[
            'image-gallery-modal',
            { 'image-gallery-modal-buttons': showButtons },
        ]"
        @click="hideModal"
        @mousemove="handleModalUpdateButtons"
        @mouseleave="handleModalUpdateButtons">
        <img
            :src="mediaGetVariantUrl(modelValue[showModalImage] as Media)"
            class="image-gallery-modal-image" />
        <div
            class="image-gallery-modal-prev"
            @click.stop="handleModalPrevImage"></div>
        <div
            class="image-gallery-modal-next"
            @click.stop="handleModalNextImage"></div>
        <div class="image-gallery-modal-close" @click="hideModal">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6"
                viewBox="0 0 24 24">
                <title>Close</title>
                <path
                    d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"
                    fill="currentColor" />
            </svg>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Media } from "../helpers/api.types";
import { mediaGetVariantUrl } from "../helpers/media";
import { openDialog } from "./SMDialog";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";

const emits = defineEmits(["update:modelValue"]);
const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
        required: true,
    },
    showEditor: {
        type: Boolean,
        default: false,
        required: false,
    },
});

const showModalImage = ref(null);
let showButtons = ref(false);
let mouseMoveTimeout = null;

const showGalleryModal = (index) => {
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
            showModalImage.value = props.modelValue.length - 1;
        }
    }
};

const handleModalNextImage = () => {
    handleModalUpdateButtons();

    if (showModalImage.value !== null) {
        if (showModalImage.value < props.modelValue.length - 1) {
            showModalImage.value++;
        } else {
            showModalImage.value = 0;
        }
    }
};

const handleAddToGallery = async () => {
    let result = await openDialog(SMDialogMedia, {
        allowUpload: true,
        multiple: true,
    });

    if (result) {
        const mediaResult = result as Media[];
        let newValue = props.modelValue;
        let galleryIds = new Set(newValue.map((item) => item.id));

        mediaResult.forEach((item) => {
            if (!galleryIds.has(item.id)) {
                newValue.push(item);
                galleryIds.add(item.id);
            }
        });

        emits("update:modelValue", newValue);
    }
};

const handleRemoveItem = async (id: string) => {
    const newList = props.modelValue.filter((item) => item.id !== id);
    emits("update:modelValue", newList);
};

onMounted(() => {
    document.addEventListener("keydown", handleKeyDown);
});

onBeforeUnmount(() => {
    document.removeEventListener("keydown", handleKeyDown);
});
</script>

<style lang="scss">
// .image-gallery {
//     display: grid;
//     grid-template-columns: 1fr 1fr;
//     gap: 15px;

//     .image-gallery-image {
//         cursor: pointer;
//         max-width: 100%;
//         max-height: 100%;
//         object-fit: contain;
//     }
// }

// @media (min-width: 768px) {
//     .image-gallery {
//         grid-template-columns: 1fr 1fr 1fr;
//     }
// }

// @media (min-width: 1024px) {
//     .image-gallery {
//         grid-template-columns: 1fr 1fr 1fr 1fr;
//     }
// }

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

.sm-gallery-item:hover .item-delete {
    display: block;
}
</style>
