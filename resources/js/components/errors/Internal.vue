<template>
    <SMPage no-breadcrumbs>
        <div class="page-error internal">
            <div class="image"></div>
            <div class="content">
                <h1>The cat has broken something</h1>
                <p>
                    We are working to fix that what was broken. Please try again
                    later.
                </p>
            </div>
        </div>
    </SMPage>
</template>

<script setup lang="ts"></script>

<style lang="scss">
.page-error.internal .image {
    background-image: url("/img/500.jpg");
}
</style>
