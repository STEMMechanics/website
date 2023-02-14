<template>
    <div
        class="carousel"
        @mouseover="handleMouseOver"
        @mouseleave="handleMouseLeave">
        <div ref="slides" class="carousel-slides">
            <slot></slot>
        </div>
        <div class="carousel-slide-prev" @click="handleSlidePrev">
            <ion-icon name="chevron-back-outline" />
        </div>
        <div class="carousel-slide-next" @click="handleSlideNext">
            <ion-icon name="chevron-forward-outline" />
        </div>
        <div class="carousel-slide-indicators">
            <div
                v-for="(indicator, index) in slideElements"
                :key="index"
                :class="[
                    'carousel-slide-indicator-item',
                    { highlighted: currentSlide == index },
                ]"
                @click="handleIndicator(index)"></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue";

const slides = ref(null);
let slideElements = ref([]);
let currentSlide = ref(0);
let maxSlide = ref(0);
let intervalRef = null;
const mutationObserver = ref(null);

onMounted(() => {
    connectMutationObserver();
    handleUpdate();
    startAutoSlide();
});

onUnmounted(() => {
    stopAutoSlide();
    disconnectMutationObserver();
});

const handleMouseOver = () => {
    stopAutoSlide();
};

const handleMouseLeave = () => {
    startAutoSlide();
};

const handleSlidePrev = () => {
    if (currentSlide.value == 0) {
        currentSlide.value = maxSlide;
    } else {
        currentSlide.value--;
    }

    updateSlidePositions();
};

const handleSlideNext = () => {
    if (currentSlide.value == maxSlide.value) {
        currentSlide.value = 0;
    } else {
        currentSlide.value++;
    }

    updateSlidePositions();
};

const handleIndicator = (index) => {
    currentSlide.value = index;
    updateSlidePositions();
};

const handleUpdate = () => {
    slideElements.value = slides.value.querySelectorAll(".carousel-slide");
    maxSlide.value = slideElements.value.length - 1;

    updateSlidePositions();
};

const updateSlidePositions = () => {
    slideElements.value.forEach((slide, index) => {
        slide.style.transform = `translateX(${
            100 * (index - currentSlide.value)
        }%)`;
    });
};

const startAutoSlide = () => {
    if (intervalRef == null) {
        intervalRef = window.setInterval(() => {
            handleSlideNext();
        }, 7000);
    }
};

const stopAutoSlide = () => {
    if (intervalRef != null) {
        window.clearInterval(intervalRef);
        intervalRef = null;
    }
};

const connectMutationObserver = () => {
    mutationObserver.value = new MutationObserver(handleUpdate);

    mutationObserver.value.observe(slides.value, {
        attributes: false,
        childList: true,
        characterData: true,
        subtree: true,
    });
};

const disconnectMutationObserver = () => {
    mutationObserver.value.disconnect();
};
</script>

<style lang="scss">
.carousel {
    position: relative;
    height: 28rem;
    background: #eee;
    overflow: hidden;

    &:hover {
        .carousel-slide-prev,
        .carousel-slide-next,
        .carousel-slide-indicators {
            opacity: 1;
        }
    }

    .carousel-slide-prev,
    .carousel-slide-next {
        position: absolute;
        top: 50%;
        font-size: 300%;
        -webkit-transform: translateY(-50%) scale(1);
        transform: translateY(-50%) scale(1);
        cursor: pointer;
        color: #fff;
        transform-origin: center center;
        transition: transform 0.2s ease-in-out, opacity 0.2s ease-in-out;
        opacity: 0.75;

        svg {
            filter: drop-shadow(0px 0px 2px rgba(0, 0, 0, 1));
        }

        &:hover {
            -webkit-transform: translateY(-50%) scale(1.25);
            transform: translateY(-50%) scale(1.25);
        }
    }

    .carousel-slide-prev {
        left: 1rem;
    }

    .carousel-slide-next {
        right: 1rem;
    }

    .carousel-slide-indicators {
        position: absolute;
        display: flex;
        justify-content: center;
        align-items: center;
        bottom: 0.25rem;
        width: 100%;
        height: 2rem;
        opacity: 0.75;
        transition: opacity 0.2s ease-in-out;

        .carousel-slide-indicator-item {
            height: map-get($spacer, 1);
            width: map-get($spacer, 1);
            border: 1px solid white;
            border-radius: 50%;
            cursor: pointer;
            font-size: 80%;
            margin: 0 calc(#{map-get($spacer, 1)} / 3);
            color: #fff;
            filter: drop-shadow(0px 0px 2px rgba(0, 0, 0, 1));

            &.highlighted {
                background-color: white;
            }
        }
    }
}

@media only screen and (max-width: 400px) {
    .carousel {
        .carousel-slide-prev,
        .carousel-slide-next {
            font-size: 150%;
        }
    }
}
</style>
