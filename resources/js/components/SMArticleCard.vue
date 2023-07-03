<template>
    <router-link
        :to="{ name: 'article', params: { slug: props.article.slug } }"
        class="article-card bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-72">
        <div
            class="h-48 bg-cover bg-center rounded-t-xl relative"
            :style="{
                backgroundImage: `url(${mediaGetVariantUrl(
                    props.article.hero,
                    'medium'
                )})`,
            }"></div>
        <div class="p-4 text-xs text-gray-7">
            {{ computedDate(props.article.publish_at) }}
        </div>
        <h3 class="px-4 mb-3 font-500 text-gray-7">
            {{ props.article.title }}
        </h3>
        <p class="p-4 text-sm text-gray-7">
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
