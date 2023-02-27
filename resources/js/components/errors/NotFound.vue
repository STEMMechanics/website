<template>
    <SMPage no-breadcrumbs>
        <div class="page-error not-found">
            <div class="image"></div>
            <div class="content">
                <h1>Opps</h1>
                <p>The page you asked for was not found</p>
            </div>
        </div>
    </SMPage>
</template>

<script setup lang="ts"></script>

<style lang="scss">
.page-error.not-found .image {
    background-image: url("/img/404.jpg");
}
</style>
