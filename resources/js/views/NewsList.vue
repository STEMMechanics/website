<template>
    <SMPage class="news-list">
        <template #container>
            <SMMessage
                v-if="message"
                icon="alert-circle-outline"
                type="error"
                :message="message"
                class="mt-5" />
            <SMPanelList
                :loading="loading"
                :not-found="!loading && posts.length == 0"
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
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { Ref, ref } from "vue";
import SMMessage from "../components/SMMessage.vue";
import SMPanel from "../components/SMPanel.vue";
import SMPanelList from "../components/SMPanelList.vue";
import { api } from "../helpers/api";

import { Post, PostCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";

const message = ref("");
const loading = ref(true);
const posts: Ref<Post[]> = ref([]);

const handleLoad = async () => {
    message.value = "";

    api.get({
        url: "/posts",
        params: {
            limit: 5,
        },
    })
        .then((result) => {
            const data = result.data as PostCollection;

            posts.value = data.posts;
            posts.value.forEach((post) => {
                post.publish_at = new SMDate(post.publish_at, {
                    format: "ymd",
                    utc: true,
                }).format("yyyy/MM/dd HH:mm:ss");
            });
        })
        .catch((error) => {
            message.value =
                error.data?.message || "The server is currently not available";
        })
        .finally(() => {
            loading.value = false;
        });
};

handleLoad();
</script>
