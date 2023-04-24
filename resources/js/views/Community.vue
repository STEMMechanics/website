<template>
    <SMMastHead title="Community"
        >STEMMechanics has an active community across multiple channels. By
        joining our communities, you agree to follow the
        <router-link :to="{ name: 'code-of-conduct' }"
            >Code of Conduct</router-link
        >.</SMMastHead
    >
    <SMContainer>
        <div class="communities">
            <a
                :href="community.url"
                class="community-card"
                v-for="(community, index) in communities"
                :key="index">
                <div
                    class="thumbnail"
                    :style="{
                        backgroundImage: `url(${community.thumbnail})`,
                    }"></div>
                <h3 class="title">{{ community.title }}</h3>
                <p class="content">
                    {{ community.content }}
                </p>
            </a>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";

const communities = [
    {
        thumbnail: "/img/community-discord.jpg",
        url: "https://discord.gg/yNzk4x7mpD",
        title: "Discord",
        content:
            "A vibrant community for discussion, user support, showcases... and custom emoji!",
    },
    {
        thumbnail: "/img/community-minecraft.jpg",
        url: "/minecraft",
        title: "Minecraft",
        content:
            "Our usual hang-out to kill zombies and build redstone contraptions.",
    },
    {
        thumbnail: "/img/community-github.jpg",
        url: "https://github.com/stemmechanics",
        title: "GitHub",
        content: "All our open-source projects. Send bug reports here.",
    },
    {
        thumbnail: "/img/community-youtube.jpg",
        url: "https://youtube.com/stemmechanics",
        title: "YouTube",
        content: "Channel for official STEMMechanics videos.",
    },
    {
        thumbnail: "/img/community-facebook.jpg",
        url: "https://facebook.com/stemmechanics",
        title: "Facebook",
        content: "Community for discussions and showcasing workshops.",
    },
    {
        thumbnail: "/img/community-mastodon.jpg",
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
