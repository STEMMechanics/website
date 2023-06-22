<template>
    <SMMastHead title="Community"
        >STEMMechanics has an active community across multiple channels. By
        joining our communities, you agree to follow the
        <router-link :to="{ name: 'code-of-conduct' }"
            >Code of Conduct</router-link
        >.</SMMastHead
    >
    <div class="max-w-7xl mx-auto px-4 pt-8">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
            <a
                :href="community.url"
                class="min-w-84 decoration-none bg-white border-1 border-gray-3 rounded-xl transition hover:shadow-md"
                v-for="(community, index) in communities"
                :key="index">
                <div
                    class="h-36 bg-cover bg-no-repeat bg-center rounded-t-xl"
                    :style="{
                        backgroundImage: `url(${community.thumbnail})`,
                    }"></div>
                <h2 class="p-4">{{ community.title }}</h2>
                <p class="text-sm text-gray-5 px-4 pb-8">
                    {{ community.content }}
                </p>
            </a>
        </div>
    </div>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";

const communities = [
    {
        thumbnail: "/assets/community-discord.webp",
        url: "https://discord.gg/yNzk4x7mpD",
        title: "Discord",
        content:
            "A vibrant community for discussion, user support, showcases... and custom emoji!",
    },
    {
        thumbnail: "/assets/community-minecraft.webp",
        url: "/minecraft",
        title: "Minecraft",
        content:
            "Our usual hang-out to kill zombies and build redstone contraptions.",
    },
    {
        thumbnail: "/assets/community-github.webp",
        url: "https://github.com/stemmechanics",
        title: "GitHub",
        content: "All our open-source projects. Send bug reports here.",
    },
    {
        thumbnail: "/assets/community-youtube.webp",
        url: "https://youtube.com/stemmechanics",
        title: "YouTube",
        content: "Channel for official STEMMechanics videos.",
    },
    {
        thumbnail: "/assets/community-facebook.webp",
        url: "https://facebook.com/stemmechanics",
        title: "Facebook",
        content: "Community for discussions and showcasing workshops.",
    },
    {
        thumbnail: "/assets/community-mastodon.webp",
        url: "https://mastodon.au/@stemmechanics",
        title: "Mastodon",
        content: "Connect with us in the Fediverse.",
    },
];
</script>

<style lang="scss">
.page-community {
    .communities {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;

        .community-card {
            text-decoration: none;
            color: var(--base-color-text);
            background-color: var(--base-color-light);
            box-shadow: var(--base-shadow);

            &:hover {
                filter: none;

                .thumbnail {
                    filter: brightness(115%);
                }
            }

            .thumbnail {
                aspect-ratio: 16 / 9;
                background-position: center;
                background-size: cover;
                background-color: var(--card-background-color);
                margin-bottom: 24px;
            }

            .title {
                margin: 0;
                padding: 0 16px;
                word-break: break-word;
                color: var(--primary-color);
            }

            .content {
                font-size: 90%;
                padding: 0 16px 16px 16px;
            }
        }
    }
}

@media (min-width: 640px) {
    .page-community .communities {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 768px) {
    .page-community .communities {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>
