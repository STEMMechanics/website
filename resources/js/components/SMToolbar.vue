<template>
    <div class="toolbar">
        <slot></slot>
    </div>
</template>

<style lang="scss">
.toolbar {
    display: flex;
    width: 100%;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

@media screen and (max-width: 768px) {
    .toolbar {
        flex-direction: column;
        margin-bottom: 32px;

        .control-group {
            margin-bottom: 0;
        }
    }
}
</style>
