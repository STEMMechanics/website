<template>
    <div class="sm-image-gallery">
        <div
            class="sm-image-gallery-inner"
            :style="{ transform: `translateX(-${slideIndex * slideWidth}px)` }">
            <div
                class="sm-image-gallery-slide"
                v-for="(image, index) in images"
                :key="index">
                <img
                    :src="image"
                    class="sm-image-gallery-image"
                    @click="showModal(index)" />
            </div>
        </div>
        <div
            class="sm-image-gallery-arrow sm-image-gallery-arrow-left"
            @click="prevSlide"
            :class="{ disabled: slideIndex === 0 }">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M19 12H5"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
                <path
                    d="M12 19L5 12L12 5"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
            </svg>
        </div>
        <div
            class="sm-image-gallery-arrow sm-image-gallery-arrow-right"
            @click="nextSlide"
            :class="{ disabled: slideIndex === images.length - visibleSlides }">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M5 12H19"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
                <path
                    d="M12 5L19 12L12 19"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round" />
            </svg>
        </div>
        <div
            v-if="showModalFlag"
            class="sm-image-gallery-modal"
            @click="hideModal">
            <img
                :src="images[slideIndex]"
                class="sm-image-gallery-modal-image" />
            <div class="sm-image-gallery-modal-close" @click="hideModal">
                &times;
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "SMImageGallery",
    props: {
        images: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            slideIndex: 0,
            slideWidth: 0,
            showModalFlag: false,
            visibleSlides: 0,
        };
    },
    mounted() {
        this.slideWidth = this.$el.offsetWidth;
        window.addEventListener("resize", this.handleResize);
        document.addEventListener("keydown", this.handleKeyDown);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
        document.removeEventListener("keydown", this.handleKeyDown);
    },
    methods: {
        nextSlide() {
            if (this.slideIndex < this.images.length - this.visibleSlides) {
                this.slideIndex++;
            } else {
                this.slideIndex = this.images.length - this.visibleSlides;
            }
        },
        prevSlide() {
            if (this.slideIndex > 0) {
                this.slideIndex--;
            } else {
                this.slideIndex = 0;
            }
        },
        showModal(index) {
            this.slideIndex = index;
            this.showModalFlag = true;
            document.addEventListener("keydown", this.handleKeyDown);
        },
        hideModal() {
            this.showModalFlag = false;
            document.removeEventListener("keydown", this.handleKeyDown);
        },
        handleResize() {
            this.slideWidth = this.$el.offsetWidth;
            this.slideIndex = 0;

            this.visibleSlides = Math.floor(
                this.$el.clientWidth / this.slideWidth
            );
            if (this.slideIndex >= this.images.length - this.visibleSlides) {
                this.slideIndex = this.images.length - this.visibleSlides;
            }
        },
        handleKeyDown(event) {
            if (event.key === "ArrowLeft") {
                if (this.showModalFlag) {
                    if (this.slideIndex > 0) {
                        this.slideIndex--;
                    }
                }
            } else if (event.key === "ArrowRight") {
                if (this.showModalFlag) {
                    if (this.slideIndex < this.images.length - 1) {
                        this.slideIndex++;
                    }
                }
            } else if (event.key === "Escape") {
                this.hideModal();
            }
        },
    },
};
</script>

<style scoped>
.sm-image-gallery {
    position: relative;
    overflow: hidden;
    margin: 20px auto;
    max-height: 100px;
    display: flex;
    justify-content: center;
}

.sm-image-gallery-inner {
    display: flex;
    transition: transform 0.3s ease-in-out;
    height: 100%;
}

.sm-image-gallery-slide {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
    margin-right: 10px;
    flex-shrink: 0;
}

.sm-image-gallery-image {
    cursor: pointer;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.sm-image-gallery-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background-color: rgba(0, 0, 0, 0.3);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    transition: background-color 0.3s ease-in-out;
}

.sm-image-gallery-arrow.disabled {
    pointer-events: none;
    background-color: rgba(0, 0, 0, 0.1);
}

.sm-image-gallery-arrow:hover {
    background-color: rgba(0, 0, 0, 0.5);
}

.sm-image-gallery-arrow-left {
    left: 10px;
}

.sm-image-gallery-arrow-right {
    right: 10px;
}

.sm-image-gallery-arrow svg {
    width: 100%;
    height: 100%;
    fill: none;
}

.sm-image-gallery-arrow svg path {
    stroke-width: 2;
}

.sm-image-gallery-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
}

.sm-image-gallery-modal-image {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.sm-image-gallery-modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 30px;
    font-weight: bold;
    color: white;
    cursor: pointer;
    transition: color 0.3s ease-in-out;
}

.sm-image-gallery-modal-close:hover {
    color: rgba(255, 255, 255, 0.7);
}
</style>
