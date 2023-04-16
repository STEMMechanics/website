<template>
    <div class="card">
        <div v-if="slots.header" class="card-header">
            <slot name="header"></slot>
        </div>
        <div v-if="slots.body || slots.default``" class="card-body">
            <slot name="body"></slot>
            <slot></slot>
        </div>
        <div v-if="slots.footer" class="card-footer">
            <slot name="footer"></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useSlots } from "vue";

const slots = useSlots();
</script>

<style lang="scss"></style>
