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
                <router-link
                    :to="{ name: 'article', params: { slug: article.slug } }"
                    class="article-card"
                    v-for="(article, idx) in articles"
                    :key="idx">
                    <div
                        class="thumbnail"
                        :style="{
                            backgroundImage: `url(${mediaGetVariantUrl(
                                article.hero,
                                'medium'
                            )})`,
                        }"></div>
                    <div class="info">
                        {{ article.user.display_name }} -
                        {{ computedDate(article.publish_at) }}
                    </div>
                    <h3 class="title">{{ article.title }}</h3>
                    <p class="content">
                        {{ excerpt(article.content) }}
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
import { Article, ArticleCollection } from "../helpers/api.types";
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

const computedDate = (date) => {
    return new SMDate(date, { format: "yMd" }).format("d MMMM yyyy");
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
