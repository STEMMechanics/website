<template>
    <SMRow>
        <SMColumn style="gap: 15px">
            <SMButton label="Button" />
            <SMButton type="primary" label="Primary Button" />
            <SMButton type="submit" label="Submit Button" />
            <SMButton type="secondary" label="Secondary Button" />
            <SMButton type="danger" label="Danger Button" />
        </SMColumn>
        <SMColumn style="gap: 15px">
            <SMButton label="Button" disabled />
            <SMButton type="primary" label="Primary Button" disabled />
            <SMButton type="submit" label="Submit Button" disabled />
            <SMButton type="secondary" label="Secondary Button" disabled />
            <SMButton type="danger" label="Danger Button" disabled />
        </SMColumn>
    </SMRow>
</template>

<script setup lang="ts">
import SMButton from "../components/SMButton.vue";
</script>
