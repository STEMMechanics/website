<template>
    <div :class="['pagination', props.size]">
        <div
            :class="['item', 'prev', { disabled: computedDisablePrevButton }]"
            @click="handleClickPrev">
            <ion-icon name="chevron-back-outline" />
            <span class="text">Prev</span>
        </div>
        <div
            :class="['item', 'page', { active: page == props.modelValue }]"
            v-for="(page, idx) of computedPages"
            :key="idx"
            @click="handleClickPage(page)">
            {{ page }}
        </div>
        <div
            :class="['item', 'next', { disabled: computedDisableNextButton }]"
            @click="handleClickNext">
            <span class="text">Next</span>
            <ion-icon name="chevron-forward-outline" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, watch } from "vue";

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
    size: {
        type: String,
        default: "",
        required: false,
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
 *
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

<style lang="scss">
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: var(--header-font-family);
    font-size: 90%;
    font-weight: 600;
    margin: 24px auto;
    // box-shadow: var(--base-shadow);

    &.small {
        font-size: 75%;
        .item {
            padding: 10px 12px;

            &.prev,
            &.next {
                ion-icon {
                    padding: 0;
                }

                .text {
                    display: none;
                }
            }
        }
    }

    .item {
        display: flex;
        cursor: pointer;
        background-color: var(--pagination-color);
        padding: 12px 16px;
        // border-right: 1px solid rgba(0, 0, 0, 0.1);

        &.page {
            width: 44px;
            justify-content: center;
        }

        &.active {
            background-color: var(--pagination-color-active);
        }

        &:first-of-type {
            border-left-width: 0;
        }

        &:last-of-type {
            border-right-width: 0;
        }

        &.prev ion-icon {
            padding-right: 12px;
        }

        &.next ion-icon {
            padding-left: 12px;
        }

        &:hover:not(.active):not(.disabled) {
            background-color: var(--pagination-color-hover);
        }

        &.disabled {
            cursor: not-allowed;
            color: var(--pagination-color-disabled-text);
            background-color: var(--pagination-color-disabled);
        }
    }
}

@media only screen and (max-width: 768px) {
    .pagination {
        .item {
            &.prev,
            &.next {
                ion-icon {
                    padding: 1px 0;
                }

                .text {
                    display: none;
                }
            }
        }
    }
}
</style>
