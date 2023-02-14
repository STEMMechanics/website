<template>
    <SMPage no-breadcrumbs>
        <div class="page-error forbidden">
            <div class="image"></div>
            <div class="content">
                <h1>The cat says no!</h1>
                <p>You do not have the needed access to see this page</p>
            </div>
        </div>
    </SMPage>
</template>

<script setup lang="ts">
import SMPage from "../SMPage.vue";
</script>

<style lang="scss">
.page-error.forbidden .image {
    background-image: url("/img/403.jpg");
}
</style>
