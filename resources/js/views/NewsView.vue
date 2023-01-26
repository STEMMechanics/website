<template>
    <SMContainer :loading="pageLoading" full class="page-post-view">
        <SMPageError :error="error">
            <div
                class="heading-image"
                :style="{
                    backgroundImage: `url('${post.hero_url}')`,
                }"></div>
            <SMContainer>
                <div class="heading-info">
                    <h1>{{ post.title }}</h1>
                    <div class="date-author">
                        <font-awesome-icon icon="fa-solid fa-calendar" />
                        {{ formattedPublishAt(post.publish_at) }}, by
                        {{ post.user_username }}
                    </div>
                </div>
                <component :is="formattedContent" ref="content"></component>
            </SMContainer>
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
.page-post-view {
    .heading-image {
        background-color: #eee;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        height: 15rem;
    }

    .heading-info {
        padding: 0 map-get($spacer, 3);

        h1 {
            text-align: left;
            margin-bottom: 0.5rem;
            text-overflow: ellipsis;
            overflow: hidden;
            word-wrap: break-word;
        }

        .date-author {
            font-size: 80%;

            svg {
                margin-right: 0.5rem;
            }
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

@media only screen and (max-width: 768px) {
    .page-post-view .heading-image {
        height: 10rem;
    }
}
</style>
