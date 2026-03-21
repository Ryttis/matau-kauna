<script setup>
import FlashError from '@/Components/App/FlashError.vue'
import Header from '@/Components/App/Header.vue'
</script>

<template>
    <div class="guest-app-layout">
        <Header />
        <div class="guest-app-layout__main">
            <div class="content">
                <FlashError />
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.guest-app-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.guest-app-layout :deep(.header) {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
}

.guest-app-layout__main {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding-top: var(--app-header-height, 66px);
    box-sizing: border-box;
    min-height: 100vh;
}

.content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    min-height: calc(100vh - var(--app-header-height, 66px));
}
</style>
