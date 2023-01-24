<template>
    <div
        class="carousel-slide"
        :style="{ backgroundImage: `url('${imageUrl}')` }">
        <div v-if="imageUrl == null" class="carousel-slide-loading">
            <font-awesome-icon icon="fa-solid fa-spinner" pulse />
        </div>
        <div v-else class="carousel-slide-body">
            <div class="carousel-slide-content">
                <div class="carousel-slide-content-inner">
                    <h3>{{ title }}</h3>
                    <p v-if="content">{{ content }}</p>
                    <div class="carousel-slide-body-buttons">
                        <SMButton v-if="url" :to="url" :label="cta" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { ref } from "vue";
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

let imageUrl = ref(null);

const handleLoad = async () => {
    try {
        let result = await axios.get(`media/${props.image}`);
        if (result.data.medium) {
            imageUrl.value = result.data.medium.url;
        }
    } catch (error) {
        imageUrl.value = "";
    }
};

handleLoad();
</script>

<style lang="scss">
.carousel-slide {
    position: absolute;
    transition: all 0.5s;
    width: 100%;
    height: 100%;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;

    .carousel-slide-loading {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;

        svg {
            color: rgba(0, 0, 0, 0.1);
            font-size: 300%;
        }
    }

    .carousel-slide-body {
        display: flex;
        align-items: center;
        height: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;

        .carousel-slide-content {
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
        }

        h3 {
            color: #fff;
            font-size: 200%;
            max-width: 600px;
            margin: 0;
            text-shadow: 0 0 8px rgba(0, 0, 0, 1);
        }

        p {
            color: #fff;
            margin-bottom: 2rem;
            max-width: 600px;
            text-shadow: 0 0 8px rgba(0, 0, 0, 1);
        }

        .carousel-slide-body-buttons {
            text-align: right;
            max-width: 600px;
        }

        .button {
            display: inline-block;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.5);
            background: transparent;
            border-color: #fff;

            &:hover {
                color: #333;
            }
        }
    }
}

@media (max-width: 768px) {
    .carousel-slide {
        .carousel-slide-body {
            padding: 0;

            .carousel-slide-content {
                width: 100%;
                max-width: 100%;
                height: 100%;
                margin: 0;
                padding-left: 5rem;
                padding-right: 5rem;
                border-radius: 0;
            }
        }
    }
}
</style>
