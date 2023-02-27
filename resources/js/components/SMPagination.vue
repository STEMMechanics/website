<template>
    <div class="sm-pagination">
        <ion-icon
            name="chevron-back-outline"
            :class="[{ disabled: computedDisablePrevButton }]"
            @click="handleClickPrev" />
        <span class="sm-pagination-info">{{ computedPaginationInfo }}</span>
        <ion-icon
            name="chevron-forward-outline"
            :class="[{ disabled: computedDisableNextButton }]"
            @click="handleClickNext" />
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
const computedPaginationInfo = computed(() => {
    if (props.total == 0) {
        return "0 - 0 of 0";
    }

    const start = (props.modelValue - 1) * props.perPage + 1;
    const end = start + props.perPage - 1;

    return `${start} - ${end} of ${props.total}`;
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
 *
 * @param {MouseEvent} $event The mouse event.
 */
const handleClickPrev = ($event: MouseEvent): void => {
    if (
        $event.target &&
        ($event.target as HTMLElement).classList.contains("disabled") ==
            false &&
        props.modelValue > 1
    ) {
        emits("update:modelValue", props.modelValue - 1);
    }
};

/**
 * Handle click on next button
 *
 * @param {MouseEvent} $event The mouse event.
 */
const handleClickNext = ($event: MouseEvent): void => {
    if (
        $event.target &&
        ($event.target as HTMLElement).classList.contains("disabled") ==
            false &&
        props.modelValue < computedTotalPages.value
    ) {
        emits("update:modelValue", props.modelValue + 1);
    }
};
</script>

<style lang="scss">
.sm-pagination {
    display: flex;
    justify-content: center;
    align-items: center;

    ion-icon {
        border: 1px solid $secondary-color;
        border-radius: 4px;
        padding: 0.25rem;

        cursor: pointer;
        transition: color 0.1s ease-in-out, background-color 0.1s ease-in-out;
        color: $font-color;

        &.disabled {
            cursor: not-allowed;
            color: $secondary-color;
        }

        &:not(.disabled) {
            &:hover {
                background-color: $secondary-color;
                color: #eee;
            }
        }
    }

    .sm-pagination-info {
        margin: 0 map-get($spacer, 3);
    }
}
</style>
