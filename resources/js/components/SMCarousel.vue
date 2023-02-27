<template>
    <div
        class="sm-carousel"
        @mouseover="handleMouseOver"
        @mouseleave="handleMouseLeave">
        <div ref="slides" class="sm-carousel-slides">
            <slot></slot>
        </div>
        <div class="sm-carousel-slide-prev" @click="handleClickSlidePrev">
            <ion-icon name="chevron-back-outline" />
        </div>
        <div class="sm-carousel-slide-next" @click="handleClickSlideNext">
            <ion-icon name="chevron-forward-outline" />
        </div>
        <div class="sm-carousel-slide-indicators">
            <div
                v-for="(indicator, index) in slideElements"
                :key="index"
                :class="[
                    'sm-carousel-slide-indicator-item',
                    { highlighted: currentSlide == index },
                ]"
                @click="handleClickIndicator(index)"></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, Ref, ref } from "vue";

/**
 * Reference to slides element.
 */
const slides: Ref<HTMLElement | null> = ref(null);

/**
 * The list of slide elements.
 */
let slideElements: Ref<NodeList | null> = ref(null);

/**
 * Index of the current slide.
 */
let currentSlide = ref(0);

/**
 * The maximum number of slides.
 */
let maxSlide = ref(0);

/**
 * The window interval reference to slide the carousel.
 */
let intervalRef: number | null = null;

/**
 * The active mutation observer.
 */
const mutationObserver: Ref<MutationObserver | null> = ref(null);

/**
 * Handle the user moving the mouse over the carousel.
 */
const handleMouseOver = () => {
    stopAutoSlide();
};

/**
 * Handle the user moving the mouse leaving the carousel.
 */
const handleMouseLeave = () => {
    startAutoSlide();
};

/**
 * Handle the user clicking the previous slider indicator.
 */
const handleClickSlidePrev = () => {
    if (currentSlide.value == 0) {
        currentSlide.value = maxSlide.value;
    } else {
        currentSlide.value--;
    }

    updateSlidePositions();
};

/**
 * Handle the user clicking the next slider indicator.
 */
const handleClickSlideNext = () => {
    if (currentSlide.value == maxSlide.value) {
        currentSlide.value = 0;
    } else {
        currentSlide.value++;
    }

    updateSlidePositions();
};

/**
 * Handle the user clicking a slider indicator.
 *
 * @param {number} index The slide to move to.
 */
const handleClickIndicator = (index: number) => {
    currentSlide.value = index;
    updateSlidePositions();
};

/**
 * Handle slides added/removed from the carousel and update the data/indicators.
 */
const handleCarouselUpdate = () => {
    if (slides.value != null) {
        slideElements.value = slides.value.querySelectorAll(".carousel-slide");
        maxSlide.value = slideElements.value.length - 1;
    }

    updateSlidePositions();
};

/**
 * Update the style transform of each slide.
 */
const updateSlidePositions = () => {
    if (slideElements.value != null) {
        slideElements.value.forEach((slide, index) => {
            (slide as HTMLElement).style.transform = `translateX(${
                100 * (index - currentSlide.value)
            }%)`;
        });
    }
};

/**
 * Start the carousel slider.
 */
const startAutoSlide = () => {
    if (intervalRef == null) {
        intervalRef = window.setInterval(() => {
            handleClickSlideNext();
        }, 7000);
    }
};

/**
 * Stop the carousel slider.
 */
const stopAutoSlide = () => {
    if (intervalRef != null) {
        window.clearInterval(intervalRef);
        intervalRef = null;
    }
};

/**
 * Connect the mutation observer to the slider.
 */
const connectMutationObserver = () => {
    if (slides.value != null) {
        mutationObserver.value = new MutationObserver(handleCarouselUpdate);

        mutationObserver.value.observe(slides.value, {
            attributes: false,
            childList: true,
            characterData: true,
            subtree: true,
        });
    }
};

/**
 * Disconnect the mutation observer from the slider.
 */
const disconnectMutationObserver = () => {
    if (mutationObserver.value) {
        mutationObserver.value.disconnect();
    }
};

onMounted(() => {
    connectMutationObserver();
    handleCarouselUpdate();
    startAutoSlide();
});

onUnmounted(() => {
    stopAutoSlide();
    disconnectMutationObserver();
});
</script>

<style lang="scss">
.sm-carousel {
    position: relative;
    height: 28rem;
    background: #eee;
    overflow: hidden;

    &:hover {
        .sm-carousel-slide-prev,
        .sm-carousel-slide-next,
        .sm-carousel-slide-indicators {
            opacity: 1;
        }
    }

    .sm-carousel-slide-prev,
    .sm-carousel-slide-next {
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

        ion-icon {
            filter: drop-shadow(0px 0px 2px rgba(0, 0, 0, 1));
        }

        &:hover {
            -webkit-transform: translateY(-50%) scale(1.25);
            transform: translateY(-50%) scale(1.25);
        }
    }

    .sm-carousel-slide-prev {
        left: 1rem;
        filter: drop-shadow(0px 0px 2px rgba(0, 0, 0, 1));
    }

    .sm-carousel-slide-next {
        right: 1rem;
        filter: drop-shadow(0px 0px 2px rgba(0, 0, 0, 1));
    }

    .sm-carousel-slide-indicators {
        position: absolute;
        display: flex;
        justify-content: center;
        align-items: center;
        bottom: 0.25rem;
        width: 100%;
        height: 2rem;
        opacity: 0.75;
        transition: opacity 0.2s ease-in-out;

        .sm-carousel-slide-indicator-item {
            height: 12px;
            width: 12px;
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
    .sm-carousel {
        .sm-carousel-slide-prev,
        .sm-carousel-slide-next {
            font-size: 150%;
        }
    }
}
</style>
