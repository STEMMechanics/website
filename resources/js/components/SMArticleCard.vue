<template>
    <router-link
        :to="{ name: 'article', params: { slug: props.article.slug } }"
        class="article-card">
        <div
            class="thumbnail"
            :style="{
                backgroundImage: `url(${mediaGetVariantUrl(
                    props.article.hero,
                    'medium'
                )})`,
            }"></div>
        <div class="info">
            {{ props.article.user.display_name }} -
            {{ computedDate(props.article.publish_at) }}
        </div>
        <h3 class="title">{{ props.article.title }}</h3>
        <p class="content">
            {{ excerpt(props.article.content) }}
        </p>
    </router-link>
</template>

<script setup lang="ts">
import { Article } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { mediaGetVariantUrl } from "../helpers/media";
import { excerpt } from "../helpers/string";

const props = defineProps({
    article: {
        type: Object as () => Article,
        required: true,
    },
});

const computedDate = (date) => {
    return new SMDate(date, { format: "yMd" }).format("d MMMM yyyy");
};
</script>

<style lang="scss">
a.article-card {
    text-decoration: none;
    color: var(--card-color-text);
    margin-bottom: 48px;

    &:hover {
        filter: none;

        .thumbnail {
            filter: brightness(115%);
        }
    }

    .thumbnail {
        aspect-ratio: 16 / 9;
        border-radius: 7px;
        background-position: center;
        background-size: cover;
        background-color: var(--card-color);
        box-shadow: var(--base-shadow);
        margin-bottom: 24px;
    }

    .info {
        font-size: 80%;
    }

    .title {
        margin: 16px 0;
        word-break: break-word;
    }

    .content {
        font-size: 90%;
    }
}
</style>
