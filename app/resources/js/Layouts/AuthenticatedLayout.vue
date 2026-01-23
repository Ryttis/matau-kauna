<script setup>
import Header from '@/Components/App/Header.vue'
import Sidebar from '@/Components/App/Sidebar.vue'
</script>

<template>
    <div class="wrapper">
        <Header />

        <div class="body">
            <Sidebar />

            <main class="content">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.body {
    flex: 1;
    display: flex;
    background: #fff;
}

.content {
    flex: 1;
    padding: 24px;
}
</style>
