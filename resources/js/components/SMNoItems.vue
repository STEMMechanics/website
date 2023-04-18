<template>
    <div class="no-items">
        <ion-icon name="alert-circle-outline" />
        <p>{{ props.text }}</p>
    </div>
</template>

<script setup lang="ts">
const props = defineProps({
    text: {
        type: String,
        default: "No items found",
        required: false,
    },
});
</script>

<style lang="scss">
.no-items {
    margin-top: 32px;
    text-align: center;

    ion-icon {
        font-size: 400%;
    }

    p {
        margin: 0;
        font-size: 130%;
    }
}
</style>
