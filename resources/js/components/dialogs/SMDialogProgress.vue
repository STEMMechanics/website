<template>
    <div
        class="fixed top-0 left-0 w-full h-full bg-black bg-op-20 backdrop-blur"></div>
    <div
        class="fixed top-0 left-0 right-0 bottom-0 flex-justify-center flex-items-center flex">
        <div
            class="flex flex-col m-4 border-1 bg-white rounded-xl text-gray-5 px-4 md:px-12 py-4 md:py-8 max-w-200 w-full overflow-hidden">
            <h2 class="mb-2">{{ props.title }}</h2>
            <div
                v-for="(row, index) in props.rows"
                class="flex flex-col text-xs my-4"
                :key="index">
                <div class="w-full bg-gray-3 h-3 mb-2 rounded-2">
                    <div
                        class="bg-sky-600 h-3 rounded-2"
                        :style="{
                            width: `${props.progress[index]}%`,
                        }"></div>
                </div>
                <p class="m-0"></p>
                {{ row }}
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
const props = defineProps({
    title: {
        type: String,
        default: "",
        required: false,
    },
    rows: {
        type: Array,
        default: () => [],
        required: false,
    },
    progress: {
        type: Array,
        default: () => [],
        required: false,
    },
});
</script>
