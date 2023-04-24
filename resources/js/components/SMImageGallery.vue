<template>
    <div class="image-gallery" ref="gallery">
        <div
            class="image-gallery-inner"
            :style="{ transform: `translateX(-${sliderOffset}px)` }">
            <div
                class="image-gallery-slide"
                v-for="(image, index) in images"
                :key="index">
                <img
                    :src="imageSize('small', image as string)"
                    class="image-gallery-image"
                    @click="showModal(index)" />
            </div>
        </div>
        <div
            v-if="!hidePrevArrow"
            class="image-gallery-arrow image-gallery-arrow-left"
            @click="prevSlide">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M12 19L5 12L12 5"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
            </svg>
        </div>
        <div
            v-if="!hideNextArrow"
            class="image-gallery-arrow image-gallery-arrow-right"
            @click="nextSlide">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M12 5L19 12L12 19"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
            </svg>
        </div>
        <div
            v-if="showModalImage !== null"
            class="image-gallery-modal"
            @click="hideModal">
            <img
                :src="images[showModalImage]"
                class="image-gallery-modal-image" />
            <div class="image-gallery-modal-close" @click="hideModal">
                &times;
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { imageSize } from "../helpers/image";

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
});

const gallery = ref(null);
const showModalImage = ref(null);
const visibleSlides = ref(0);
const slideWidths = ref([]);
const sliderWidth = ref(0);
const sliderOffset = ref(0);
const touchStartX = ref(null);
const swipeDistance = ref(0);

const handleTouchStart = (event) => {
    touchStartX.value = event.touches[0].clientX;
    swipeDistance.value = 0;
};

const handleTouchMove = (event) => {
    if (touchStartX.value === null) {
        return;
    }

    const touchX = event.touches[0].clientX;
    swipeDistance.value = touchX - touchStartX.value;
    const sliderOffsetMax =
        slideWidths.value.reduce((acc, curr) => acc + curr, 0) -
        sliderWidth.value;

    if (sliderOffset.value + swipeDistance.value < 0) {
        sliderOffset.value = 0;
    } else if (sliderOffset.value + swipeDistance.value > sliderOffsetMax) {
        sliderOffset.value = sliderOffsetMax;
    } else {
        sliderOffset.value += swipeDistance.value;
    }
};

const handleTouchEnd = () => {
    touchStartX.value = null;
    swipeDistance.value = 0;
};

const handleResize = () => {
    const slides = gallery.value.querySelectorAll(
        ".image-gallery-slide"
    ) as HTMLElement[];
    slideWidths.value = Array.from(slides).map((slide) => {
        const computedStyle = window.getComputedStyle(slide);
        const marginLeft = parseFloat(computedStyle.marginLeft);
        const marginRight = parseFloat(computedStyle.marginRight);
        const paddingLeft = parseFloat(computedStyle.paddingLeft);
        const paddingRight = parseFloat(computedStyle.paddingRight);
        return (
            slide.offsetWidth +
            marginLeft +
            marginRight +
            paddingLeft +
            paddingRight
        );
    });
    sliderWidth.value = gallery.value.querySelector(
        ".image-gallery-inner"
    ).offsetWidth;

    let visibleWidth = 0;
    for (
        visibleSlides.value = 0;
        visibleSlides.value < slideWidths.value.length;
        visibleSlides.value++
    ) {
        visibleWidth += slideWidths.value[visibleSlides.value];
        if (visibleWidth > sliderWidth.value) {
            break;
        }
    }
};

const nextSlide = () => {
    handleResize();

    const diff = Math.abs(visibleSlides.value - slideWidths.value.length);
    if (visibleSlides.value < slideWidths.value.length && diff > 1) {
        const width = sliderOffset.value + sliderWidth.value;
        let sum = 0;
        let index = 0;
        for (; index < slideWidths.value.length; index++) {
            if (sum > width) {
                break;
            }
            sum += slideWidths.value[index];
        }

        sliderOffset.value = sum - sliderWidth.value;
    }
};

const prevSlide = () => {
    if (sliderOffset.value > 0) {
        let sum = 0;
        let index = 0;
        for (; index < slideWidths.value.length; index++) {
            if (sum + slideWidths.value[index] >= sliderOffset.value) {
                break;
            }
            sum += slideWidths.value[index];
        }

        sliderOffset.value = sum;
    }
};

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
    handleResize();
    window.addEventListener("resize", handleResize);
    document.addEventListener("keydown", handleKeyDown);
    gallery.value.addEventListener("touchstart", handleTouchStart);
    gallery.value.addEventListener("touchmove", handleTouchMove);
    gallery.value.addEventListener("touchend", handleTouchEnd);
});

onBeforeUnmount(() => {
    gallery.value.removeEventListener("touchstart", handleTouchStart);
    gallery.value.removeEventListener("touchmove", handleTouchMove);
    gallery.value.removeEventListener("touchend", handleTouchEnd);
    window.removeEventListener("resize", handleResize);
    document.removeEventListener("keydown", handleKeyDown);
});

const hidePrevArrow = computed(() => {
    return sliderOffset.value <= 0;
});

const hideNextArrow = computed(() => {
    return false;
    // const sum = slideWidths.value.reduce((acc, curr) => acc + curr, 0);
    // console.log(sum, sliderWidth.value, sliderOffset.value);
    // return sliderWidth.value + sliderOffset.value >= sum;
});
</script>

<style lang="scss">
.image-gallery {
    position: relative;
    overflow: hidden;
    margin: 20px auto;
    max-height: 200px;
    display: flex;
    justify-content: center;
}

.image-gallery-inner {
    display: flex;
    transition: transform 0.3s ease-in-out;
    // height: 100%;
}

.image-gallery-slide {
    // display: flex;
    // justify-content: center;
    // align-items: center;
    height: 100%;
    margin-left: 5px;
    margin-right: 5px;
    flex-shrink: 0;

    &:first-of-type {
        margin-left: 0;
    }

    &:last-of-type {
        margin-right: 0;
    }
}

.image-gallery-image {
    cursor: pointer;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.image-gallery-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    transition: background-color 0.3s ease-in-out;
    color: #fff;
    filter: drop-shadow(0px 0px 2px rgb(0, 0, 0));
    transition: transform 0.2s ease-in-out;
}

.image-gallery-arrow.disabled {
    pointer-events: none;
}

.image-gallery-arrow:hover {
    transform: translateY(-50%) scale(1.25);
}

.image-gallery-arrow-left {
    left: 0;
}

.image-gallery-arrow-right {
    right: 0;
}

.image-gallery-arrow svg {
    width: 100%;
    height: 100%;
    fill: none;
}

.image-gallery-arrow svg path {
    stroke-width: 2;
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
    cursor: pointer;
    pointer-events: none;
    z-index: 1000;
}

.image-gallery-modal * {
    pointer-events: auto;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}

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
    display: block;
    justify-content: center;
    align-items: center;
    font-size: 30px;
    font-weight: bold;
    color: white;
    cursor: pointer;
    transition: color 0.3s ease-in-out;
}

.image-gallery-modal-close:hover {
    color: rgba(255, 255, 255, 0.7);
}
</style>
