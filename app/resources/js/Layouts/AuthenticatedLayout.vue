<script setup>
import FlashError from '@/Components/App/FlashError.vue'
import Header from '@/Components/App/Header.vue'
import Sidebar from '@/Components/App/Sidebar.vue'
</script>

<template>
    <div class="authenticated-layout">
        <Header />

        <div class="authenticated-layout__main">
            <div class="body">
                <Sidebar />

                <main class="content">
                    <FlashError />
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.authenticated-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.authenticated-layout :deep(.header) {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
}

.authenticated-layout__main {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding-top: var(--app-header-height, 66px);
    box-sizing: border-box;
    min-height: 100vh;
}

.body {
    flex: 1;
    display: flex;
    background: var(--color-white, #fff);
}

.content {
    flex: 1;
    padding: 24px;
}
</style>
