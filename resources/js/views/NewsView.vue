<template>
    <SMContainer :loading="pageLoading">
        <SMPageError :error="error">
            <div class="post-page">
                <div class="heading">
                    <div
                        class="hero-image"
                        :style="{
                            backgroundImage: `url('${post.hero_url}')`,
                        }"></div>
                    <div class="info">
                        <h1>{{ post.title }}</h1>
                        <div class="date-author">
                            {{ formattedPublishAt(post.publish_at) }}, by
                            {{ post.user_username }}
                        </div>
                    </div>
                </div>
                <component :is="formattedContent" ref="content"></component>
            </div>
        </SMPageError>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import axios from "axios";
import { useRoute } from "vue-router";
import SMPageError from "../components/SMPageError.vue";
import { fullMonthString, timestampUtcToLocal } from "../helpers/common";
import { useApplicationStore } from "../store/ApplicationStore";

const applicationStore = useApplicationStore();
const route = useRoute();
let post = ref({});
let content = ref(null);
let error = ref(0);
let pageLoading = ref(true);

const loadData = async () => {
    if (route.params.slug) {
        try {
            let res = await axios.get(
                `posts?slug==${route.params.slug}&limit=1`
            );
            if (!res.data.posts) {
                error.value = 500;
            } else {
                if (res.data.total == 0) {
                    error.value = 404;
                } else {
                    post.value = res.data.posts[0];

                    post.value.publish_at = timestampUtcToLocal(
                        post.value.publish_at
                    );

                    applicationStore.setDynamicTitle(post.value.title);

                    try {
                        let result = await axios.get(
                            `media/${post.value.hero}`
                        );
                        post.value.hero_url = result.data.medium.url;
                    } catch (error) {
                        /* empty */
                    }

                    try {
                        let result = await axios.get(
                            `users/${post.value.user_id}`
                        );
                        post.value.user_username = result.data.user.username;
                    } catch (error) {
                        /* empty */
                    }
                }
            }
        } catch (err) {
            error.value = 500;
        }
    }

    pageLoading.value = false;
};

const formattedPublishAt = (dateStr) => {
    const date = new Date(Date.parse(dateStr));
    return (
        fullMonthString[date.getMonth()] +
        " " +
        date.getDate() +
        ", " +
        date.getFullYear()
    );
};

const formattedContent = computed(() => {
    let html = post.value.content;
    if (html) {
        const regex = new RegExp(
            `<a ([^>]*?)href="${import.meta.env.APP_URL}(.*?>.*?)</a>`,
            "ig"
        );
        html = html.replaceAll(regex, '<router-link $1to="$2</router-link>');
    }

    return {
        template: `<div class="content">${html}</div>`,
    };
});

loadData();
</script>

<style lang="scss">
.post-page {
    margin: 0 auto;
    width: 100%;
    max-width: 1200px;

    .heading {
        display: flex;
        // justify-content: center;
        align-items: center;

        background-color: #eee;
        padding: 1rem;
        border-radius: 24px;
    }

    h1 {
        margin: 0 0 map-get($spacer, 3) 0;
        text-align: left;
    }

    .date-author {
        font-size: 80%;
    }

    .hero-image {
        width: 12rem;
        height: 12rem;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        padding: map-get($spacer, 3);
        border-radius: 100%;
        border: 3px solid $primary-color-dark;
        box-shadow: 0 0 0 2px #fff inset;
        margin-right: map-get($spacer, 4);
        transition: transform 2s ease-in-out;
        transition-delay: 0s;

        &:hover {
            transform: rotateZ(750deg);
            transition-delay: 3s;
        }
    }

    .content {
        margin-top: map-get($spacer, 4);
        line-height: 1.5rem;
        padding: 0 map-get($spacer, 3);

        a span {
            color: $primary-color !important;
        }
    }
}
</style>
