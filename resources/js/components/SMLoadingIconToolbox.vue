<template>
    <div class="sm-loading-icon-toolbox">
        <div class="loading-logo"></div>
        <div class="loading-bar"></div>
    </div>
</template>

<style lang="scss">
.sm-loading-icon-toolbox {
    display: inline-block;
    position: relative;
    width: 80px;
    height: 80px;

    .loading-logo {
        background-image: url("/img/logo-small.png");
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
        height: 130px;
        width: 130px;
    }

    .loading-bar {
        width: 130px;
        height: 10px;
        background: #cfcfcf;
        position: relative;
        overflow: hidden;
        border: 1px solid #aaa;
    }

    .loading-bar::before {
        content: "";
        width: 68px;
        height: 10px;
        background: $primary-color;
        position: absolute;
        left: -34px;
        animation: bluebar 1.5s infinite ease;
    }
}

@keyframes bluebar {
    50% {
        left: 96px;
    }
}
</style>
