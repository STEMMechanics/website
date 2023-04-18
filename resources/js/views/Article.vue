<template>
    <div
        class="thumbnail"
        :style="{ backgroundImage: `url('${backgroundImageUrl}')` }"></div>
    <SMContainer narrow>
        <h1 class="title">{{ post.title }}</h1>
        <div class="author">By {{ post.user.username }}</div>
        <div class="date">{{ formattedDate(post.publish_at) }}</div>
        <SMHTML :html="post.content" class="content" />
        <SMAttachments :attachments="post.attachments || []" />
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, Ref } from "vue";
import { useRoute } from "vue-router";
import SMAttachments from "../components/SMAttachments.vue";
import SMHTML from "../components/SMHTML.vue";
import { api } from "../helpers/api";
import { Post, PostCollection, User } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { useApplicationStore } from "../store/ApplicationStore";
import { mediaGetVariantUrl } from "../helpers/media";

const applicationStore = useApplicationStore();

/**
 * The post data.
 */
let post: Ref<Post> = ref({
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
 * Post user.
 */
let postUser: User | null = null;

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
                url: "/posts/",
                params: {
                    slug: `=${slug}`,
                    limit: 1,
                },
            });

            const data = result.data as PostCollection;

            if (data && data.posts && data.total && data.total > 0) {
                post.value = data.posts[0];

                post.value.publish_at = new SMDate(post.value.publish_at, {
                    format: "ymd",
                    utc: true,
                }).format("yyyy/MM/dd HH:mm:ss");

                backgroundImageUrl.value = mediaGetVariantUrl(post.value.hero);
                applicationStore.setDynamicTitle(post.value.title);
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
        margin-top: 16px;
        font-weight: 700;
    }

    .date {
        margin-top: 16px;
        font-weight: 700;
        filter: brightness(175%);
    }

    .content {
        margin-top: 24px;
    }
}

@media only screen and (max-width: 768px) {
    .sm-page-post-view .sm-heading-image {
        height: 10rem;
    }
}
</style>
