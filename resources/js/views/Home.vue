<template>
    <header class="bg-hero">
        <div
            class="max-w-7xl flex flex-row mx-auto px-4 pt-32 pb-32 lg:pt-36 gap-16 text-white">
            <div class="flex-1 max-w-2xl">
                <h1 class="leading-normal text-4xl lg:leading-normal">
                    Join the fun!
                </h1>
                <p class="mt-4">
                    To keep up with our ever-changing world, it's important to
                    encourage and support a new generation of curious minds who
                    love science, engineering, art, and leadership.
                </p>
                <p class="mt-4">
                    Our fun and exciting workshops can unlock countless
                    opportunities for new ideas and improvements, giving kids
                    the skills and tools they need to solve any problem that
                    comes their way.
                </p>
            </div>
        </div>
        <div class="flex justify-end">
            <p class="text-white text-xs bg-black px-4 py-1 mb-5 mr-10">
                Steady Hand Game in Ravenshoe
            </p>
        </div>
    </header>
    <section id="news" class="w-full pt-12 pb-8 bg-sky-100 dark:bg-dark-8">
        <div class="max-w-7xl mx-auto">
            <h2 class="font-semibold text-xl md:text-2xl px-6 lg:px-4 mb-4">
                Latest News
            </h2>
            <SMLoading v-if="articlesLoading" />
            <div
                v-else-if="
                    !articlesLoading &&
                    articlesError.length == 0 &&
                    articles.length > 0
                "
                class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 px-4">
                <SMArticleCard
                    v-for="article in articles"
                    :article="article"
                    :key="article.id" />
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
                    {{ articlesError || "No articles where found" }}
                </p>
            </div>
        </div>
    </section>
    <section class="max-w-7xl flex flex-row mx-auto px-4 py-24 lg:py-36 gap-16">
        <div
            class="flex-1 lg:flex hidden justify-end flex-self-center rounded-lg bg-gray-900 aspect-video relative overflow-clip max-h-82 w-120 h-283">
            <img
                class="w-full h-full object-cover"
                src="/assets/home-green-screen.webp" />
        </div>
        <div class="flex-1">
            <h2
                class="font-medium leading-normal lg:text-4xl lg:leading-normal text-4xl">
                Build skills while having a great time
            </h2>
            <p class="text-xl mt-4">
                To keep up with our ever-changing world, it's important to
                encourage and support a new generation of curious minds who love
                science, engineering, art, and leadership.
            </p>
            <div class="flex flex-row gap-4 mt-8 flex-justify-center">
                <router-link
                    :to="{ name: 'workshops' }"
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition bg-green-600 hover:bg-green-500 text-white">
                    Explore Workshops
                </router-link>
            </div>
        </div>
    </section>
    <section
        id="workshops"
        class="w-full py-12 lg:py-16 bg-fuchsia-50 dark:bg-dark-8">
        <div class="max-w-7xl mx-auto">
            <h2 class="font-semibold text-3xl md:text-4xl px-6 lg:px-4 mb-14">
                Upcoming workshops
            </h2>
            <SMLoading v-if="eventsLoading" />
            <div
                v-else-if="
                    !eventsLoading &&
                    eventsError.length == 0 &&
                    events.length > 0
                "
                class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 px-4">
                <SMEventCard
                    v-for="event in events"
                    :event="event"
                    :key="event.id" />
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
                    {{ eventsError || "No workshops scheduled at this time" }}
                </p>
            </div>
        </div>
    </section>
    <div class="bg-minecraft">
        <section
            class="max-w-7xl flex flex-col mx-auto px-4 pt-32 pb-26 lg:pt-36 lg:pb-46 text-white">
            <h2
                class="font-medium leading-normal lg:text-4xl lg:leading-normal text-4xl">
                Play Minecraft with us
            </h2>
            <p class="text-xl mt-4">
                We invite you to join us on our
                <router-link :to="{ name: 'minecraft' }">
                    Minecraft server</router-link
                >
                where you can participate in weekly challenges and mini-games.
            </p>
            <div
                class="flex flex-row gap-4 mt-8 items-center flex-justify-center">
                <img
                    src="/assets/home-minecraft-edu.webp"
                    loading="lazy"
                    class="h-24" />
                <p class="text-xl mt-4">
                    We also offer workshops for
                    <a
                        href="https://education.minecraft.net/en-us/discover/what-is-minecraft"
                        target="_blank">
                        Minecraft Education</a
                    >
                    , where you can learn to make it rain rabbits or grow
                    flowers wherever you walk, all without the need for a school
                    account.
                </p>
            </div>
            <div class="flex flex-row gap-4 mt-8 flex-justify-center">
                <img
                    src="/assets/home-minecraft-address.webp"
                    loading="lazy"
                    class="max-w-140 w-full" />
            </div>
        </section>
    </div>
    <section
        class="max-w-7xl flex flex-row mx-auto px-4 pt-24 pb-8 lg:pt-36 lg:pb-8 gap-16">
        <div
            class="flex-1 lg:flex hidden justify-end flex-self-center rounded-lg bg-gray-900 aspect-video relative overflow-clip max-h-82 w-120 h-283">
            <img
                class="w-full h-full object-cover"
                src="/assets/home-discord.webp" />
        </div>
        <div class="flex-1">
            <h2
                class="font-medium leading-normal lg:text-4xl lg:leading-normal text-4xl">
                And the support doesn't stop!
            </h2>
            <p class="text-xl mt-4">
                Though the workshop has come to a close, we remain available to
                assist you via email and Discord with any projects you undertake
                at home. We are always happy to help.
            </p>
            <div class="flex flex-row gap-4 mt-8 flex-justify-center">
                <a
                    role="button"
                    href="https://discord.gg/yNzk4x7mpD"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white">
                    Join Discord
                </a>
                <router-link
                    :to="{ name: 'contact' }"
                    role="button"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md text-black transition border-1 border-gray-400 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800">
                    Contact Us
                </router-link>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { api, getApiResultData } from "../helpers/api";
import { ArticleCollection, EventCollection } from "../helpers/api.types";
import { SMDate } from "../helpers/datetime";
import SMArticleCard from "../components/SMArticleCard.vue";
import SMEventCard from "../components/SMEventCard.vue";
import SMLoading from "../components/SMLoading.vue";

const events = ref([]);
const articles = ref([]);

let eventsLoading = ref(true);
let eventsError = ref("");

let articlesLoading = ref(true);
let articlesError = ref("");

const viewLoad = async () => {
    eventsLoading.value = true;
    eventsError.value = "";

    articlesLoading.value = true;
    articlesError.value = "";

    try {
        await Promise.all([
            api
                .get({
                    url: "/events",
                    params: {
                        limit: 10,
                        sort: "start_at",
                        start_at: `>${new SMDate("now").format(
                            "yyyy-MM-dd hh:mm:ss"
                        )}`,
                    },
                })
                .then((eventsResult) => {
                    const eventsData =
                        getApiResultData<EventCollection>(eventsResult);

                    if (eventsData && eventsData.events) {
                        events.value = [];

                        for (const event of eventsData.events) {
                            if (
                                event.status === "open" ||
                                event.status === "soon"
                            ) {
                                events.value.push(event);
                                if (events.value.length === 4) break;
                            }
                        }
                    }
                })
                .catch((error) => {
                    if (error.status != 404) {
                        eventsError.value =
                            "An error occured retrieving the events";
                    }
                })
                .finally(() => {
                    eventsLoading.value = false;
                }),
            api
                .get({
                    url: "/articles",
                    params: {
                        limit: 4,
                    },
                })
                .then((articlesResult) => {
                    const articlesData =
                        getApiResultData<ArticleCollection>(articlesResult);

                    if (articlesData && articlesData.articles) {
                        articles.value = articlesData.articles;
                    }
                })
                .catch((error) => {
                    if (error.status != 404) {
                        articlesError.value =
                            "An error occured retrieving the posts";
                    }
                })
                .finally(() => {
                    articlesLoading.value = false;
                }),
        ]);
    } catch {
        /* empty */
    }
};

viewLoad();
</script>

<style lang="scss">
.bg-hero {
    margin-top: -70px;
    background-image: linear-gradient(
            to right,
            rgba(0, 0, 0, 0.7),
            rgba(0, 0, 0, 0.2)
        ),
        url("https://www.stemmechanics.com.au/assets/home-hero.webp");
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
}

.bg-minecraft {
    background-image: url("/assets/home-minecraft.webp");
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
}

@media (min-width: 1024px) {
    #news .article-card:nth-child(4),
    #workshops .event-card:nth-child(4) {
        display: none;
    }
}
</style>
