<template>
    <SMMastHead title="Blog" />
    <SMContainer class="flex-grow-1">
        <SMInput
            type="text"
            label="Search articles"
            v-model="searchInput"
            @keyup.enter="handleClickSearch"
            @blur="handleClickSearch">
            <template #append
                ><SMButton
                    type="primary"
                    label="Search"
                    icon="search-outline"
                    @click="handleClickSearch"
            /></template>
        </SMInput>
        <SMLoading v-if="pageLoading" large />
        <SMNoItems v-else-if="posts.length == 0" text="No Articles Found" />
        <template v-else>
            <SMPagination
                v-if="postsTotal > postsPerPage"
                v-model="postsPage"
                :total="postsTotal"
                :per-page="postsPerPage" />
            <div class="posts">
                <router-link
                    :to="{ name: 'article', params: { slug: post.slug } }"
                    class="article-card"
                    v-for="(post, idx) in posts"
                    :key="idx">
                    <div
                        class="thumbnail"
                        :style="{
                            backgroundImage: `url(${mediaGetVariantUrl(
                                post.hero,
                                'medium'
                            )})`,
                        }"></div>
                    <div class="info">
                        {{ post.user.display_name }} -
                        {{ computedDate(post.publish_at) }}
                    </div>
                    <h3 class="title">{{ post.title }}</h3>
                    <p class="content">
                        {{ excerpt(post.content) }}
                    </p>
                </router-link>
            </div>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import { Ref, ref, watch } from "vue";
import SMPagination from "../components/SMPagination.vue";
import { api } from "../helpers/api";
import { Post, PostCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import { mediaGetVariantUrl } from "../helpers/media";
import SMMastHead from "../components/SMMastHead.vue";
import SMInput from "../components/SMInput.vue";
import SMButton from "../components/SMButton.vue";
import { excerpt } from "../helpers/string";
import SMLoading from "../components/SMLoading.vue";
import SMNoItems from "../components/SMNoItems.vue";

const message = ref("");
const pageLoading = ref(true);
const posts: Ref<Post[]> = ref([]);

const postsPerPage = 24;
let postsPage = ref(1);
let postsTotal = ref(0);

let searchInput = ref("");

const handleClickSearch = () => {
    postsPage.value = 1;
    handleLoad();
};

/**
 * Load the page data.
 */
const handleLoad = () => {
    message.value = "";
    pageLoading.value = true;
    posts.value = [];

    let params = {
        limit: postsPerPage,
        page: postsPage.value,
    };

    if (searchInput.value.length > 0) {
        params[
            "filter"
        ] = `(title:${searchInput.value},OR,content:${searchInput.value})`;
    }

    api.get({
        url: "/posts",
        params: params,
    })
        .then((result) => {
            const data = result.data as PostCollection;

            posts.value = data.posts;
            postsTotal.value = data.total;
            posts.value.forEach((post) => {
                post.publish_at = new SMDate(post.publish_at, {
                    format: "ymd",
                    utc: true,
                }).format("yyyy/MM/dd HH:mm:ss");
            });
        })
        .catch((error) => {
            if (error.status != 404) {
                message.value =
                    error.data?.message ||
                    "The server is currently not available";
            }
        })
        .finally(() => {
            pageLoading.value = false;
        });
};

const computedDate = (date) => {
    return new SMDate(date, { format: "yMd" }).format("d MMMM yyyy");
};

watch(
    () => postsPage.value,
    () => {
        handleLoad();
    }
);

handleLoad();
</script>

<style lang="scss">
.page-blog {
    .posts {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;

        .article-card {
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
    }
}

@media (min-width: 768px) {
    .page-blog .posts {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1024px) {
    .page-blog .posts {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>
