<template>
    <div class="sm-loading-icon-balls">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
</template>

<style lang="scss">
.sm-loading-icon-balls {
    display: inline-block;
    position: relative;
    width: 3em;
    height: 0.5em;

    div {
        position: absolute;
        top: 0em;
        width: 0.5em;
        height: 0.5em;
        border-radius: 50%;
        background: var(--base-color-light);
        animation-timing-function: cubic-bezier(0, 1, 1, 0);
    }
    div:nth-child(1) {
        left: 0.3em;
        animation: sm-loading-icon1 0.6s infinite;
    }
    div:nth-child(2) {
        left: 0.3em;
        animation: sm-loading-icon2 0.6s infinite;
    }
    div:nth-child(3) {
        left: 1.2em;
        animation: sm-loading-icon2 0.6s infinite;
    }
    div:nth-child(4) {
        left: 2.1em;
        animation: sm-loading-icon3 0.6s infinite;
    }
    @keyframes sm-loading-icon1 {
        0% {
            transform: scale(0);
        }
        100% {
            transform: scale(1);
        }
    }
    @keyframes sm-loading-icon3 {
        0% {
            transform: scale(1);
        }
        100% {
            transform: scale(0);
        }
    }
    @keyframes sm-loading-icon2 {
        0% {
            transform: translate(0, 0);
        }
        100% {
            transform: translate(0.9em, 0);
        }
    }
}
</style>
