<template>
    <div class="flex flex-justify-center">
        <div
            :class="[
                'flex',
                'items-center',
                'border-y-1',
                'border-l-1',
                'rounded-l-2',
                'transition',
                small
                    ? ['text-sm', 'px-2', 'py-1']
                    : ['text-lg', 'px-4', 'py-2'],
                computedDisablePrevButton
                    ? [
                          'bg-gray-2',
                          'text-gray-4',
                          'border-gray-3',
                          'cursor-not-allowed',
                      ]
                    : [
                          'hover:bg-sky-200',
                          'cursor-pointer',
                          'bg-white',
                          'border-gray',
                      ],
            ]"
            @click="handleClickPrev">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                :class="[small ? 'h-4' : 'h-6']">
                <path
                    d="M400-80 0-480l400-400 56 57-343 343 343 343-56 57Z"
                    fill="currentColor" />
            </svg>
            <span class="text">Prev</span>
        </div>
        <div
            :class="[
                'flex',
                'items-center',
                'border-y-1',
                'border-l-1',
                'border-gray',
                'transition',
                small
                    ? ['text-sm', 'px-2', 'py-1']
                    : ['text-lg', 'px-4', 'py-2'],
                page == props.modelValue
                    ? ['bg-sky-600', 'text-white']
                    : ['hover:bg-sky-200', 'cursor-pointer', 'bg-white'],
            ]"
            v-for="(page, idx) of computedPages"
            :key="idx"
            @click="handleClickPage(page)">
            {{ page }}
        </div>
        <div
            :class="[
                'flex',
                'items-center',
                'border-1',
                'rounded-r-2',
                'transition',
                small
                    ? ['text-sm', 'px-2', 'py-1']
                    : ['text-lg', 'px-4', 'py-2'],
                computedDisableNextButton
                    ? [
                          'bg-gray-2',
                          'text-gray-4',
                          'border-gray-3',
                          'cursor-not-allowed',
                      ]
                    : [
                          'hover:bg-sky-200',
                          'cursor-pointer',
                          'bg-white',
                          'border-gray',
                      ],
                ,
            ]"
            @click="handleClickNext">
            <span class="text">Next</span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                :class="[small ? 'h-4' : 'h-6']">
                <path
                    d="m304-82-56-57 343-343-343-343 56-57 400 400L304-82Z"
                    fill="currentColor" />
            </svg>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps({
    modelValue: {
        type: Number,
        required: true,
    },
    total: {
        type: Number,
        required: true,
    },
    perPage: {
        type: Number,
        required: true,
    },
    small: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const emits = defineEmits(["update:modelValue"]);

/**
 * Returns the pagination info
 */
const computedPages = computed(() => {
    let pages = [];

    let pagesRemaining =
        Math.ceil(props.total / props.perPage) - props.modelValue;
    let pagesBefore = Math.max(0, props.modelValue - 1);

    if (pagesRemaining + pagesBefore > 4) {
        if (pagesRemaining < 2) {
            pagesBefore = Math.min(pagesBefore, 4 - pagesRemaining);
        } else if (pagesBefore < 2) {
            pagesRemaining = Math.min(pagesRemaining, 4 - pagesBefore);
        } else {
            pagesRemaining = 2;
            pagesBefore = 2;
        }
    }

    for (; pagesBefore > 0; pagesBefore--) {
        pages.push(props.modelValue - pagesBefore);
    }
    pages.push(props.modelValue);
    for (let i = 1; i <= pagesRemaining; i++) {
        pages.push(props.modelValue + i);
    }

    return pages;
});

/**
 * Return the total number of pages.
 */
const computedTotalPages = computed(() => {
    return Math.ceil(props.total / props.perPage);
});

/**
 * Return if the previous button should be disabled.
 */
const computedDisablePrevButton = computed(() => {
    return props.modelValue <= 1;
});

/**
 * Return if the next button should be disabled.
 */
const computedDisableNextButton = computed(() => {
    return props.modelValue >= computedTotalPages.value;
});

/**
 * Handle click on previous button
 */
const handleClickPrev = (): void => {
    if (computedDisablePrevButton.value == false) {
        emits("update:modelValue", props.modelValue - 1);
    }
};

/**
 * Handle click on next button
 */
const handleClickNext = (): void => {
    if (computedDisableNextButton.value == false) {
        emits("update:modelValue", props.modelValue + 1);
    }
};

/**
 * Handle click on page button
 * @param {number} page The page number to display.
 */
const handleClickPage = (page: number): void => {
    emits("update:modelValue", page);
};

const totalPages = computedTotalPages.value;
if (props.modelValue < 1 || totalPages < 1) {
    emits("update:modelValue", 1);
} else {
    if (totalPages < props.modelValue) {
        emits("update:modelValue", totalPages);
    }
}
</script>
