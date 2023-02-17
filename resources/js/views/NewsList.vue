<template>
    <SMPage class="news-list">
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message"
            class="mt-5" />
        <SMPanelList
            :loading="loading"
            :not-found="posts.value?.length == 0"
            not-found-text="No news found">
            <SMPanel
                v-for="post in posts.value"
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
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { api } from "../helpers/api";
import SMMessage from "../components/SMMessage.vue";
import SMPanelList from "../components/SMPanelList.vue";
import SMPanel from "../components/SMPanel.vue";
import SMPage from "../components/SMPage.vue";
import { timestampUtcToLocal } from "../helpers/datetime";

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const loading = ref(true);
const posts = reactive([]);

const handleLoad = async () => {
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        let result = await api.get({
            url: "/posts",
            params: {
                limit: 5,
            },
        });
        posts.value = result.json.posts;

        posts.value.forEach((post) => {
            post.publish_at = timestampUtcToLocal(post.publish_at);
        });
    } catch (error) {
        formMessage.message =
            error.response?.data?.message ||
            "The server is currently not available";
    }

    loading.value = false;
};

handleLoad();
</script>
