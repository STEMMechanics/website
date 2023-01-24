<template>
    <SMDialog>
        <SMButton type="primary" label="Confirm" @click="onClick" />
    </SMDialog>
</template>

<script setup lang="ts">
import SMDialog from "./Dialog.vue";
import SMButton from "../components/SMButton.vue";

const emit = defineEmits(["confirm", "cancel"]);

const onClick = () => {
    console.log("click");
    emit("confirm");
};
</script>

<style lang="scss"></style>
