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
    align-items: center;
    gap: 20px;
}
</style>
