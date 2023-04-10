<template>
    <SMPage class="sm-page-post-view" :page-error="pageError">
        <SMContainer>
            <div class="sm-post-hero" :style="backgroundStyle"></div>
            <div class="sm-heading-info">
                <h1>{{ post.title }}</h1>
                <div class="sm-date-author small">
                    <ion-icon name="calendar-outline" />
                    {{ formattedPublishAt(post.publish_at) }}, by
                    {{ post.user.username }}
                </div>
            </div>
            <SMHTML :html="post.content" />
            <SMAttachments :attachments="post.attachments || []" />
        </SMContainer>
    </SMPage>
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

let backgroundStyle = {};

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

                backgroundStyle = {
                    backgroundImage: `url('${mediaGetVariantUrl(
                        post.value.hero
                    )}')`,
                };

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

const formattedPublishAt = (dateStr) => {
    return new SMDate(dateStr, { format: "yMd" }).format("MMMM d, yyyy");
};

handleLoad();
</script>

<style lang="scss">
.sm-page-post-view {
    .sm-container {
        width: 70%;
        padding: 64px 0;
    }

    .sm-post-hero {
        display: block;
        width: 100%;
        height: 480px;
        border-radius: 6px;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .sm-heading-info {
        padding: 0 map-get($spacer, 3);
        margin-bottom: map-get($spacer, 4);

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

    .sm-content {
        padding: 0 map-get($spacer, 3);
        line-height: 1.5rem;
    }
}

@media only screen and (max-width: 768px) {
    .sm-page-post-view .sm-heading-image {
        height: 10rem;
    }
}
</style>
