<template>
    <SMMastHead title="Blog" />
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex space-between gap-4 py-8">
            <SMInput
                type="text"
                label="Search articles"
                v-model="searchInput"
                @keyup.enter="handleSearch"
                @blur="handleSearch">
                <template #append
                    ><button
                        type="button"
                        class="font-medium px-4 py-3.1 rounded-r-2 hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        @click="handleSearch">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 -960 960 960"
                            class="h-6">
                            <path
                                d="M796-121 533-384q-30 26-69.959 40.5T378-329q-108.162 0-183.081-75Q120-479 120-585t75-181q75-75 181.5-75t181 75Q632-691 632-584.85 632-542 618-502q-14 40-42 75l264 262-44 44ZM377-389q81.25 0 138.125-57.5T572-585q0-81-56.875-138.5T377-781q-82.083 0-139.542 57.5Q180-666 180-585t57.458 138.5Q294.917-389 377-389Z"
                                fill="currentColor" />
                        </svg></button
                ></template>
            </SMInput>
        </div>
        <SMPagination
            v-if="articlesTotal > articlesPerPage"
            v-model="articlesPage"
            :total="articlesTotal"
            :per-page="articlesPerPage" />
        <SMLoading v-if="pageLoading" />
        <div
            v-else-if="articles.length > 0"
            class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
            <SMArticleCard
                v-for="(article, index) in articles"
                :key="index"
                :article="article" />
        </div>
        <div v-else class="py-12 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-24 text-gray-5">
                <path
                    d="M453-280h60v-240h-60v240Zm26.982-314q14.018 0 23.518-9.2T513-626q0-14.45-9.482-24.225-9.483-9.775-23.5-9.775-14.018 0-23.518 9.775T447-626q0 13.6 9.482 22.8 9.483 9.2 23.5 9.2Zm.284 514q-82.734 0-155.5-31.5t-127.266-86q-54.5-54.5-86-127.341Q80-397.681 80-480.5q0-82.819 31.5-155.659Q143-709 197.5-763t127.341-85.5Q397.681-880 480.5-880q82.819 0 155.659 31.5Q709-817 763-763t85.5 127Q880-563 880-480.266q0 82.734-31.5 155.5T763-197.684q-54 54.316-127 86Q563-80 480.266-80Zm.234-60Q622-140 721-239.5t99-241Q820-622 721.188-721 622.375-820 480-820q-141 0-240.5 98.812Q140-622.375 140-480q0 141 99.5 240.5t241 99.5Zm-.5-340Z"
                    fill="currentColor" />
            </svg>
            <p class="text-lg text-gray-5">
                {{ articlesError || "No posts where found" }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Ref, ref, watch } from "vue";
import SMPagination from "../components/SMPagination.vue";
import { api } from "../helpers/api";
import { Article, ArticleCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import SMMastHead from "../components/SMMastHead.vue";
import SMInput from "../components/SMInput.vue";
import SMLoading from "../components/SMLoading.vue";
import SMArticleCard from "../components/SMArticleCard.vue";

const message = ref("");
const pageLoading = ref(true);
const articles: Ref<Article[]> = ref([]);

const articlesPerPage = 24;
let articlesPage = ref(1);
let articlesTotal = ref(0);

const articlesError = ref("");

let searchInput = ref("");
let oldSearchInput = "";

const handleSearch = () => {
    if (oldSearchInput != searchInput.value) {
        oldSearchInput = searchInput.value;
        articlesPage.value = 1;
        handleLoad();
    }
};

/**
 * Load the page data.
 */
const handleLoad = () => {
    message.value = "";
    pageLoading.value = true;
    articles.value = [];

    let params = {
        limit: articlesPerPage,
        page: articlesPage.value,
    };

    if (searchInput.value.length > 0) {
        params[
            "filter"
        ] = `(title:${searchInput.value},OR,content:${searchInput.value})`;
    }

    api.get({
        url: "/articles",
        params: params,
    })
        .then((result) => {
            const data = result.data as ArticleCollection;

            articles.value = data.articles;
            articlesTotal.value = data.total;
            articles.value.forEach((article) => {
                article.publish_at = new SMDate(article.publish_at, {
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
    () => articlesPage.value,
    () => {
        handleLoad();
    },
);

handleLoad();
</script>

<style lang="scss">
.page-blog {
    .articles {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
    }
}

@media (min-width: 768px) {
    .page-blog .articles {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1024px) {
    .page-blog .articles {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>
