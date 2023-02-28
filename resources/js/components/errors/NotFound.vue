<template>
    <SMPage no-breadcrumbs>
        <div class="sm-page-error sm-error-not-found">
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
.sm-page-error.sm-error-not-found .image {
    background-image: url("/img/404.jpg");
}
</style>
