<template>
    <SMLoading class="pt-24 pb-48" v-if="pageLoading" />
    <SMPageStatus
        v-else-if="!pageLoading && pageStatus != 200"
        :status="pageStatus" />
    <template v-else>
        <div
            class="max-w-4xl mx-auto h-96 text-center mb-8 relative rounded-4 overflow-hidden">
            <div
                class="blur bg-cover bg-center absolute top-0 left-0 w-full h-full -z-1 opacity-50"
                :style="{
                    backgroundImage: `url('${backgroundImageUrl}')`,
                }"></div>
            <img :src="backgroundImageUrl" class="h-full" />
        </div>
        <div class="max-w-4xl mx-auto flex flex-col px-4">
            <h1 class="pb-2 text-gray-6">
                {{ article.title }}
            </h1>
            <div
                class="flex flex-1 flex-justify-between flex-items-center pb-4">
                <div>
                    <div class="font-bold text-gray-4">
                        {{ formattedDate(article.publish_at) }}
                    </div>
                </div>
                <router-link
                    v-if="userHasPermission('admin/articles') && article.id"
                    role="button"
                    :to="{
                        name: 'dashboard-article-edit',
                        params: { id: article.id },
                    }"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm border-1 bg-white border-sky-6 text-sky-600 text-center"
                    >Edit Article</router-link
                >
            </div>
            <SMHTML :html="article.content" />
            <SMImageGallery
                v-if="article.gallery.length > 0"
                :model-value="article.gallery" />
            <SMAttachments
                v-if="article.attachments.length > 0"
                :model-value="article.attachments || []" />
        </div>
    </template>
</template>

<script setup lang="ts">
import { ref, Ref } from "vue";
import { useRoute } from "vue-router";
import SMAttachments from "../components/SMAttachments.vue";
import { api } from "../helpers/api";
import { Article, ArticleCollection, User } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { useApplicationStore } from "../store/ApplicationStore";
import { mediaGetVariantUrl } from "../helpers/media";
import { userHasPermission } from "../helpers/utils";
import SMLoading from "../components/SMLoading.vue";
import SMPageStatus from "../components/SMPageStatus.vue";
import SMHTML from "../components/SMHTML.vue";
import SMImageGallery from "../components/SMImageGallery.vue";

const applicationStore = useApplicationStore();

/**
 * The article data.
 */
let article: Ref<Article> = ref({
    id: "",
    created_at: "",
    updated_at: "",
    title: "",
    slug: "",
    user_id: "",
    user: { display_name: "" },
    content: "",
    publish_at: "",
    hero: {},
    gallery: [],
    attachments: [],
});

/**
 * The current page error.
 */
let pageStatus = ref(200);

/**
 * Is the page loading.
 */
let pageLoading = ref(false);

/**
 * Article user.
 */
let articleUser: User | null = null;

/**
 * Thumbnail image URL.
 */
let backgroundImageUrl = ref("");

/**
 * Load the page data.
 */
const handleLoad = async () => {
    let slug = useRoute().params.slug || "";
    pageLoading.value = true;

    if (slug.length > 0) {
        let result = await api.get({
            url: "/articles",
            params: {
                slug: `=${slug}`,
                limit: 1,
            },
            callback: (result) => {
                if (result.status < 300) {
                    const data = result.data as ArticleCollection;

                    if (data && data.articles && data.total && data.total > 0) {
                        article.value = data.articles[0];

                        article.value.publish_at = new SMDate(
                            article.value.publish_at,
                            {
                                format: "ymd",
                                utc: true,
                            },
                        ).format("yyyy/MM/dd HH:mm:ss");

                        backgroundImageUrl.value = mediaGetVariantUrl(
                            article.value.hero,
                            "large",
                        );
                        applicationStore.setDynamicTitle(article.value.title);
                    } else {
                        pageStatus.value = 404;
                    }
                } else {
                    pageStatus.value = result.status;
                }

                pageLoading.value = false;
            },
        });
    } else {
        pageStatus.value = 404;
    }
};

/**
 * Format Date
 * @param dateStr Date string.
 * @returns Formatted date.
 */
const formattedDate = (dateStr) => {
    return new SMDate(dateStr, { format: "yMd" }).format("MMMM d, yyyy");
};

handleLoad();
</script>
