<template>
    <div class="sm-carousel-slide" :style="styleObject">
        <div class="sm-carousel-slide-body">
            <div class="sm-carousel-slide-content">
                <div class="sm-carousel-slide-content-inner">
                    <h3>{{ title }}</h3>
                    <p v-if="content">{{ content }}</p>
                    <div class="sm-carousel-slide-body-buttons">
                        <SMButton
                            v-if="url"
                            :to="url"
                            :label="cta"
                            type="secondary-outline" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import SMButton from "./SMButton.vue";

const props = defineProps({
    title: {
        type: String,
        default: "",
        required: true,
    },
    content: {
        type: String,
        default: "",
        required: true,
    },
    image: {
        type: String,
        default: "",
        required: true,
    },
    url: {
        type: [String, Object],
        default: "",
        required: false,
    },
    cta: {
        type: String,
        default: "View",
        required: false,
    },
});

/**
 * Carousel slide styles.
 */
let styleObject = {
    backgroundImage: `url('${props.image}')`,
};
</script>

<style lang="scss">
.sm-carousel-slide {
    position: absolute;
    transition: all 0.5s;
    width: 100%;
    height: 100%;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    overflow: hidden;

    .sm-carousel-slide-loading {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
    }

    .sm-carousel-slide-body {
        display: flex;
        align-items: center;
        height: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;

        .sm-carousel-slide-content {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: rgba(0, 0, 0, 0.75);
            width: auto;
            height: auto;
            max-width: 800px;
            padding: 2rem 3rem 1.5rem 3rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            margin-left: 3rem;
            margin-right: 3rem;
        }

        h3 {
            color: #fff;
            font-size: 200%;
            max-width: 600px;
            margin: 0 0 0.5rem 0;
            text-shadow: 0 0 8px rgba(0, 0, 0, 1);
            text-align: left;
        }

        p {
            display: inline-block;
            color: #fff;
            max-width: 600px;
            text-shadow: 0 0 8px rgba(0, 0, 0, 1);
            text-align: left;
        }

        .sm-carousel-slide-body-buttons {
            margin-top: 2rem;
            text-align: right;
            max-width: 600px;
        }

        .secondary-outline {
            border-color: #fff;
            color: #fff;

            &:hover {
                color: #333;
            }
        }
    }
}

@media only screen and (max-width: 768px) {
    .sm-carousel-slide {
        .sm-carousel-slide-body {
            padding: 0;

            .sm-carousel-slide-content {
                width: 100%;
                max-width: 100%;
                height: 100%;
                margin: 0;
                padding-left: 5rem;
                padding-right: 5rem;
                border-radius: 0;

                .sm-carousel-slide-content-inner {
                    overflow: hidden;
                }
            }

            h3 {
                font-size: 120%;
            }

            p {
                font-size: 90%;
            }

            .sm-carousel-slide-body-buttons {
                text-align: center;
            }
        }
    }
}

@media only screen and (max-width: 640px) {
    .sm-carousel-slide .sm-carousel-slide-body {
        h3,
        p {
            text-align: center;
        }
    }
}

@media only screen and (max-width: 400px) {
    .sm-carousel-slide .sm-carousel-slide-body {
        .sm-carousel-slide-content {
            padding-left: 3rem;
            padding-right: 3rem;
        }

        h3 {
            font-size: 175%;
        }

        p {
            display: none;
        }
    }
}
</style>
