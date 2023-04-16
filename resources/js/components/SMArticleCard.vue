<template>
    <router-link :to="props.to" :class="['sm-article-card', `${props.type}`]">
        <div
            class="thumbnail"
            :style="[{ backgroundImage: `url('${props.image}')` }]"></div>
        <div class="content">
            <h3>{{ props.title }}</h3>
            <p class="excerpt">{{ props.excerpt }}</p>
        </div>
    </router-link>
</template>

<script setup lang="ts">
const props = defineProps({
    to: {
        type: Object,
        required: true,
    },
    image: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: "",
        required: false,
    },
    excerpt: {
        type: String,
        required: true,
    },
});
</script>

<style lang="scss">
.sm-article-card {
    display: flex;
    height: 100%;
    flex-direction: column;
    gap: 20px;

    .thumbnail {
        aspect-ratio: 16 / 9;
        border-radius: 7px;
        background-position: center;
        background-size: cover;
        background-color: var(--card-background-color);
        box-shadow: var(--box-shadow);
    }

    &.row {
        flex-direction: row;
    }
}
</style>
