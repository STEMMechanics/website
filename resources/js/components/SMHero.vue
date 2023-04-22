<template>
    <section class="hero">
        <div class="hero-background" :style="heroStyles"></div>
        <SMContainer class="align-items-start">
            <div class="hero-content">
                <h1>{{ heroTitle }}</h1>
                <p>{{ heroExcerpt }}</p>
                <div class="hero-buttons">
                    <SMButton
                        v-if="loaded"
                        type="primary"
                        :to="{ name: 'article', params: { slug: heroSlug } }"
                        label="Read More" />
                </div>
            </div>
        </SMContainer>
        <div class="hero-caption">
            <router-link
                v-if="loaded"
                :to="{ name: 'article', params: { slug: heroSlug } }"
                >{{ heroImageTitle }}</router-link
            >
        </div>
    </section>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { api, getApiResultData } from "../helpers/api";
import { PostCollection } from "../helpers/api.types";
import { mediaGetVariantUrl } from "../helpers/media";
import { excerpt } from "../helpers/string";
import SMButton from "./SMButton.vue";

const loaded = ref(false);
let heroTitle = ref("");
let heroExcerpt = ref("");
let heroImageUrl = ref("");
let heroImageTitle = "";
let heroSlug = ref("");
const translateY = ref(0);
const heroStyles = ref({
    backgroundImage: "none",
    transform: "translateY(0px)",
});

const handleScroll = () => {
    const scrollTop = window.scrollY;
    translateY.value = scrollTop / 2.5;
};

watch(translateY, () => {
    heroStyles.value.transform = `translateY(${translateY.value}px)`;
});

onMounted(() => {
    window.addEventListener("scroll", handleScroll);
});

onBeforeUnmount(() => {
    window.removeEventListener("scroll", handleScroll);
});

const handleLoad = async () => {
    try {
        let postsResult = await api.get({
            url: "/posts",
            params: {
                limit: 3,
            },
        });

        const postsData = getApiResultData<PostCollection>(postsResult);

        if (postsData && postsData.posts) {
            const randomIndex = Math.floor(
                Math.random() * postsData.posts.length
            );
            heroTitle.value = postsData.posts[randomIndex].title;
            heroExcerpt.value = excerpt(
                postsData.posts[randomIndex].content,
                200
            );
            heroImageUrl.value = mediaGetVariantUrl(
                postsData.posts[randomIndex].hero
            );
            heroImageTitle = postsData.posts[randomIndex].hero.title;
            heroSlug.value = postsData.posts[randomIndex].slug;

            heroStyles.value.backgroundImage = `linear-gradient(to right, rgba(0, 0, 0, 0.7),rgba(0, 0, 0, 0.2)),url('${heroImageUrl.value}')`;

            loaded.value = true;
        }
    } catch {
        // empty
    }
};

handleLoad();
</script>

<style lang="scss">
.hero {
    position: relative;
    overflow: hidden;

    .hero-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .hero-content {
        position: relative;
        margin: 150px 32px 120px;
        max-width: 640px;

        h1 {
            font-size: 300%;
            margin-bottom: 20px;
            max-width: 550px;
            color: #fff;
            text-align: left;
            text-shadow: 0 0 8px #000;
        }

        p {
            max-width: 550px;
            color: #fff;
            text-align: left;
            text-shadow: 0 0 8px #000;
        }
    }

    .hero-caption {
        position: absolute;
        bottom: 14px;
        right: 30px;
        color: #ccc;
        font-size: 80%;
        padding: 6px 12px;
        background-color: rgba(0, 0, 0, 0.5);

        a {
            color: inherit;
            transition: color 0.1s ease-in-out;
            text-decoration: none;

            &:hover {
                text-decoration: none;
                color: #eee;
            }
        }
    }

    .hero-buttons {
        padding-top: 48px;

        .primary {
            background-color: var(--primary-color-dark);
        }
    }
}

@media only screen and (max-width: 640px) {
    .hero {
        .hero-content {
            margin: 150px 0;
        }

        .hero-buttons {
            .button {
                display: block;
                text-align: center;
            }
        }
    }
}
</style>
