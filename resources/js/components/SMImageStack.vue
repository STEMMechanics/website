<template>
    <div class="sm-image-stack-container">
        <div
            class="sm-image-stack"
            :style="{
                height: 300 + props.src.length * 20 + 'px',
                width: 533 + props.src.length * 40 + 'px',
            }"
            @mouseout="handleHover(-1)">
            <div
                v-for="(source, index) in props.src"
                :key="index"
                :style="{
                    top: index * 20 + 'px',
                    left: index * 40 + 'px',
                    'background-image': `url('${source}')`,
                    'z-index': frontImage == index ? 1 : null,
                }"
                class="image"
                @mouseover="handleHover(index)"></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";

const props = defineProps({
    src: {
        type: Array,
        required: true,
    },
});

const frontImage = ref(-1);

const handleHover = (index) => {
    console.log(index);
    frontImage.value = index;
};
</script>

<style lang="scss">
.sm-image-stack-container {
    display: flex;
    width: 100%;
    justify-content: center;
}

.sm-image-stack {
    position: relative;
    display: flex;

    .image {
        position: absolute;
        background-position: top left;
        background-repeat: no-repeat;
        background-size: cover;
        height: 300px;
        width: 533px;
        border-radius: 8px;
        box-shadow: var(--base-shadow);
    }
}
</style>
