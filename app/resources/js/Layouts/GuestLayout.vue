<script setup>
import Header from '@/Components/App/Header.vue'
</script>

<template>
    <div class="wrapper">
        <Header />
        <div class="content">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: calc(100vh - 100px);
}
</style>
