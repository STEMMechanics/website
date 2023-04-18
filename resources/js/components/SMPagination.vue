<template>
    <div class="pagination">
        <div
            v-if="props.modelValue > 1"
            :class="[
                'item',
                'previous',
                { disabled: computedDisablePrevButton },
            ]"
            @click="handleClickPrev">
            <ion-icon name="chevron-back-outline" />
            Previous
        </div>
        <div
            :class="['item', { active: page == props.modelValue }]"
            v-for="(page, idx) of computedPages"
            :key="idx"
            @click="handleClickPage(page)">
            {{ page }}
        </div>
        <div
            v-if="(props.modelValue + 3) * props.perPage <= props.total"
            :class="['item', 'next', { disabled: computedDisableNextButton }]"
            @click="handleClickNext">
            Next
            <ion-icon name="chevron-forward-outline" />
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
});

const emits = defineEmits(["update:modelValue"]);

/**
 * Returns the pagination info
 */
const computedPages = computed(() => {
    let pages = [];

    if (props.modelValue - 2 > 0) {
        pages.push(props.modelValue - 2);
    }

    if (props.modelValue - 1 > 0) {
        pages.push(props.modelValue - 1);
    }

    pages.push(props.modelValue);

    if (props.perPage * (props.modelValue + 1) <= props.total) {
        pages.push(props.modelValue + 1);
    }

    if (props.perPage * (props.modelValue + 2) <= props.total) {
        pages.push(props.modelValue + 2);
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
    emits("update:modelValue", props.modelValue - 1);
};

/**
 * Handle click on next button
 */
const handleClickNext = (): void => {
    emits("update:modelValue", props.modelValue + 1);
};

/**
 * Handle click on page button
 *
 * @param {number} page The page number to display.
 */
const handleClickPage = (page: number): void => {
    emits("update:modelValue", page);
};
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
    box-shadow: var(--base-shadow);

    .item {
        display: flex;
        cursor: pointer;
        background-color: var(--base-color-light);
        padding: 12px 16px;
        border-right: 1px solid rgba(0, 0, 0, 0.1);

        &.active {
            background-color: var(--primary-color);
        }

        &:first-of-type {
            border-left-width: 0;
        }

        &:last-of-type {
            border-right-width: 0;
        }

        &.previous ion-icon {
            padding-right: 12px;
        }

        &.next ion-icon {
            padding-left: 12px;
        }

        &:hover:not(.active) {
            background-color: var(--primary-color-hover);
        }
    }
}

@media (prefers-color-scheme: dark) {
    .pagination .item.active {
        background-color: var(--primary-color-light);
    }
}
</style>
