<template>
    <div class="group-buttons">
        <div
            :class="['group-button', { active: props.active == button.name }]"
            v-for="button in props.buttons"
            :key="button.name">
            <ion-icon :name="button.icon" @click="handleClick(button.name)" />
        </div>
    </div>
</template>

<script setup lang="ts">
interface Button {
    name: string;
    icon: string;
}

const props = defineProps({
    buttons: {
        type: Array as () => Button[],
        required: true,
    },
    active: {
        type: String,
        default: "",
        required: false,
    },
});

const emits = defineEmits(["click"]);

const handleClick = (name: string) => {
    emits("click", name);
};
</script>

<style lang="scss">
.group-buttons {
    display: flex;
    border: 1px solid var(--base-color-darker);
    border-radius: 8px;
    margin-bottom: 16px;

    .group-button {
        padding: 8px 12px 5px 12px;

        &:not(:last-of-type) {
            border-right: 1px solid var(--base-color-darker);
        }

        &.active ion-icon {
            color: var(--primary-color);
        }
    }
}
</style>
