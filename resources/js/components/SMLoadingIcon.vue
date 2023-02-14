<template>
    <div class="sm-loading-icon">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
</template>

<style lang="scss">
.sm-loading-icon {
    display: inline-block;
    position: relative;
    width: 80px;
    height: 80px;
}
.sm-loading-icon div {
    position: absolute;
    top: 33px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #000;
    animation-timing-function: cubic-bezier(0, 1, 1, 0);
}
.sm-loading-icon div:nth-child(1) {
    left: 8px;
    animation: sm-loading-icon1 0.6s infinite;
}
.sm-loading-icon div:nth-child(2) {
    left: 8px;
    animation: sm-loading-icon2 0.6s infinite;
}
.sm-loading-icon div:nth-child(3) {
    left: 32px;
    animation: sm-loading-icon2 0.6s infinite;
}
.sm-loading-icon div:nth-child(4) {
    left: 56px;
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
        transform: translate(24px, 0);
    }
}
</style>
