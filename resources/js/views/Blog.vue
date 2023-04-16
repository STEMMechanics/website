<template>
    <SMMastHead title="Blog" />
    <SMContainer>
        <SMInputGroup>
            <SMInput
                type="text"
                label="Search articles"
                v-model="searchInput" />
            <SMButton type="submit" label="Search" @click="handeClickSearch" />
        </SMInputGroup>
        <SMPagination
            v-model="postsPage"
            :total="postsTotal"
            :per-page="postsPerPage" />
        <div class="posts">
            <article
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
                <div class="content">
                    {{ post.content }}
                </div>
            </article>
        </div>
    </SMContainer>
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
import { mediaGetVariantUrl } from "../helpers/media";
import SMMastHead from "../components/SMMastHead.vue";
import SMInput from "../components/SMInput.vue";
import SMInputGroup from "../components/SMInputGroup.vue";
import SMForm from "../components/SMForm.vue";

const message = ref("");
const pageLoading = ref(true);
const posts: Ref<Post[]> = ref([]);

const postsPerPage = 24;
let postsPage = ref(1);
let postsTotal = ref(0);

let searchInput = ref("");

const handeClickSearch = () => {
    alert(searchInput.value);
};

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

<style lang="scss">
.posts {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;

    .article-card {
        .thumbnail {
            aspect-ratio: 16 / 9;
            border-radius: 7px;
            background-position: center;
            background-size: cover;
            background-color: var(--card-background-color);
            box-shadow: 0 5px 10px -3px #00000078;
        }
    }
}

@media (min-width: 768px) {
    .posts {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1024px) {
    .posts {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>
