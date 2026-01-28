<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
</script>

<template>
    <Head title="Kategorijos" />

    <AuthenticatedLayout>
        <div class="empty-page">
            <p>Nėra duomenų</p>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.empty-page {
    display: flex;
    align-items: center;
    justify-content: center;
    height: calc(100vh - 200px);
    color: #999;
    font-size: 18px;
}
</style>
