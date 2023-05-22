<template>
    <div class="sm-image-stack-container">
        <div
            :class="[
                'sm-image-stack',
                { 'sm-image-stack-hover': frontImage !== -1 },
            ]"
            :style="{
                height: 300 + props.src.length * 20 + 'px',
                width: 533 + props.src.length * 40 + 'px',
            }"
            @mouseout="handleHover(-1)">
            <div
                v-for="(source, index) in props.src"
                :key="index"
                :style="{
                    top: (index + 1) * 20 + 'px',
                    left: index * 40 + 'px',
                    'background-image': `url('${source}')`,
                }"
                :class="['image', { hover: frontImage == index }]"
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

    &.sm-image-stack-hover {
        .image {
            opacity: 0.5;
        }

        .hover {
            opacity: 1 !important;
            z-index: 1;
            top: 0 !important;
        }
    }

    .image {
        position: absolute;
        background-position: top left;
        background-repeat: no-repeat;
        background-size: cover;
        height: 300px;
        width: 533px;
        border-radius: 8px;
        box-shadow: var(--base-shadow);
        // box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.5);
        transition: all 0.1s ease-in-out;
    }
}
</style>
