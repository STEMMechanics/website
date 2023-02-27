<template>
    <SMPage class="sm-post-list" :loading="pageLoading">
        <template #container>
            <SMMessage
                v-if="message"
                icon="alert-circle-outline"
                type="error"
                :message="message"
                class="mt-5" />
            <SMPanelList
                :not-found="!pageLoading && posts.length == 0"
                not-found-text="No news found">
                <SMPanel
                    v-for="post in posts"
                    :key="post.id"
                    :image="post.hero"
                    :to="{ name: 'post-view', params: { slug: post.slug } }"
                    :title="post.title"
                    :date="post.publish_at"
                    :content="post.content"
                    :show-date="false"
                    button="Read More"
                    button-type="outline" />
            </SMPanelList>
            <SMPagination
                v-model="postsPage"
                :total="postsTotal"
                :per-page="postsPerPage" />
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { Ref, ref, watch } from "vue";
import SMMessage from "../components/SMMessage.vue";
import SMPagination from "../components/SMPagination.vue";
import SMPanel from "../components/SMPanel.vue";
import SMPanelList from "../components/SMPanelList.vue";
import { api } from "../helpers/api";
import { Post, PostCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";

const message = ref("");
const pageLoading = ref(true);
const posts: Ref<Post[]> = ref([]);

const postsPerPage = 9;
let postsPage = ref(1);
let postsTotal = ref(0);

/**
 * Load the page data.
 */
const handleLoad = () => {
    message.value = "";
    pageLoading.value = true;

    api.get({
        url: "/posts",
        params: {
            limit: postsPerPage,
            page: postsPage.value,
        },
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

watch(
    () => postsPage.value,
    () => {
        handleLoad();
    }
);

handleLoad();
</script>
