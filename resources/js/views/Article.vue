<template>
    <div
        class="thumbnail"
        :style="{ backgroundImage: `url('${backgroundImageUrl}')` }"></div>
    <SMContainer narrow>
        <h1 class="title">
            {{ article.title }}
        </h1>
        <SMToolbar>
            <div>
                <div class="author">By {{ article.user.username }}</div>
                <div class="date">{{ formattedDate(article.publish_at) }}</div>
            </div>
            <SMButton
                v-if="userHasPermission('admin/articles') && article.id"
                size="medium"
                type="primary"
                :to="{
                    name: 'dashboard-article-edit',
                    params: { id: article.id },
                }"
                label="Edit Article" />
        </SMToolbar>
        <SMHTML :html="article.content" class="content" />
        <SMAttachments :attachments="article.attachments || []" />
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, Ref } from "vue";
import { useRoute } from "vue-router";
import SMAttachments from "../components/SMAttachments.vue";
import SMHTML from "../components/SMHTML.vue";
import { api } from "../helpers/api";
import { Article, ArticleCollection, User } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { useApplicationStore } from "../store/ApplicationStore";
import { mediaGetVariantUrl } from "../helpers/media";
import SMToolbar from "../components/SMToolbar.vue";
import SMButton from "../components/SMButton.vue";
import { userHasPermission } from "../helpers/utils";

const applicationStore = useApplicationStore();

/**
 * The article data.
 */
let article: Ref<Article> = ref({
    title: "",
    user: { username: "" },
});

/**
 * The current page error.
 */
let pageError = ref(200);

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

    try {
        if (slug.length > 0) {
            let result = await api.get({
                url: "/articles",
                params: {
                    slug: `=${slug}`,
                    limit: 1,
                },
            });

            const data = result.data as ArticleCollection;

            if (data && data.articles && data.total && data.total > 0) {
                article.value = data.articles[0];

                article.value.publish_at = new SMDate(
                    article.value.publish_at,
                    {
                        format: "ymd",
                        utc: true,
                    }
                ).format("yyyy/MM/dd HH:mm:ss");

                backgroundImageUrl.value = mediaGetVariantUrl(
                    article.value.hero,
                    "medium"
                );
                applicationStore.setDynamicTitle(article.value.title);
            } else {
                pageError.value = 404;
            }
        } else {
            pageError.value = 404;
        }
    } catch (error) {
        /* empty */
    } finally {
        pageLoading.value = false;
    }
};

/**
 * Format Date
 *
 * @param dateStr Date string.
 * @returns Formatted date.
 */
const formattedDate = (dateStr) => {
    return new SMDate(dateStr, { format: "yMd" }).format("MMMM d, yyyy");
};

handleLoad();
</script>

<style lang="scss">
.page-article {
    .thumbnail {
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        aspect-ratio: 16 / 9;
        max-height: 640px;
        width: 100%;
    }

    .title {
        margin-top: 64px;
        text-align: left;
    }

    .author {
        // margin-top: 16px;
        font-weight: 700;
    }

    .date {
        margin-top: 8px;
        font-weight: 700;
        filter: brightness(175%);
    }

    .content {
        margin-top: 24px;
    }
}

@media only screen and (max-width: 768px) {
    .page-article-view .heading-image {
        height: #{calc(map-get($spacing, 3) * 10)};
    }
}
</style>
