<template>
    <div class="mb-4">
        <ul class="flex relative">
            <li
                v-for="tab in tabs"
                :key="tab.id"
                :class="[
                    'px-4',
                    'py-2',
                    '-mb-1px',
                    'border-1',
                    'rounded-t-2',
                    'border-gray',
                    selectedTab == tab.id
                        ? ['border-b-white']
                        : [
                              'border-x-white',
                              'border-t-white',
                              'hover:border-x-gray-3',
                              'hover:border-t-gray-3',
                          ],
                ]"
                @click="selectedTab = tab.id">
                {{ tab.label }}
            </li>
        </ul>
        <slot></slot>
    </div>
</template>

<script setup lang="ts">
import { provide, ref, useSlots, watch } from "vue";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
        required: false,
    },
});

const emits = defineEmits(["tabChanged", "update:modelValue"]);
const slots = useSlots();

const tabs = ref(
    slots
        .default()
        .map((tab) => {
            const { label, id, hide } = tab.props;
            if (hide !== true) {
                return {
                    label,
                    id,
                };
            }
        })
        .filter(Boolean),
);

const selectedTab = ref(
    props.modelValue.length == 0 ? tabs.value[0].id : props.modelValue,
);

if (props.modelValue.length == 0) {
    emits("update:modelValue", selectedTab.value);
}

watch(
    () => selectedTab.value,
    (newValue) => {
        emits("tabChanged", newValue);
        emits("update:modelValue", newValue);
    },
);

watch(
    () => props.modelValue,
    (newValue) => {
        selectedTab.value = newValue;
    },
);

provide("selectedTab", selectedTab);
</script>
