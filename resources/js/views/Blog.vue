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
        <SMNoItems v-else-if="articles.length == 0" text="No Articles Found" />
        <template v-else>
            <SMPagination
                v-if="articlesTotal > articlesPerPage"
                v-model="articlesPage"
                :total="articlesTotal"
                :per-page="articlesPerPage" />
            <div class="articles">
                <SMArticleCard
                    v-for="(article, index) in articles"
                    :key="index"
                    :article="article" />
            </div>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import { Ref, ref, watch } from "vue";
import SMPagination from "../components/SMPagination.vue";
import { api } from "../helpers/api";
import { Article, ArticleCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import SMMastHead from "../components/SMMastHead.vue";
import SMInput from "../components/SMInput.vue";
import SMButton from "../components/SMButton.vue";
import SMLoading from "../components/SMLoading.vue";
import SMNoItems from "../components/SMNoItems.vue";
import SMArticleCard from "../components/SMArticleCard.vue";

const message = ref("");
const pageLoading = ref(true);
const articles: Ref<Article[]> = ref([]);

const articlesPerPage = 24;
let articlesPage = ref(1);
let articlesTotal = ref(0);

let searchInput = ref("");

const handleClickSearch = () => {
    articlesPage.value = 1;
    handleLoad();
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
    }
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
