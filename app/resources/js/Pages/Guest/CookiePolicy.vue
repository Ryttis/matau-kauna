<script setup>
import { Head } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'

defineProps({
    title: { type: String, default: 'Slapukų informacija' },
    body: { type: String, default: '' }
})
</script>

<template>
    <Head :title="title + ' – Kauno problemos'" />
    <div class="landing static-page">
        <LandingHeader />
        <main class="static-main">
            <div class="static-container">
                <h1 class="static-title">{{ title }}</h1>
                <div v-if="body" class="static-body content-html" v-html="body"></div>
                <p v-else class="static-empty">Turinys dar neįkeltas.</p>
            </div>
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.landing {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: 'Open Sans', sans-serif;
}

.static-main {
    flex: 1;
    padding: 2rem 0 3rem;
}

.static-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.static-title {
    font-size: clamp(1.5rem, 4vw, 1.75rem);
    font-weight: 700;
    color: #000;
    margin: 0 0 1rem 0;
}

.static-body {
    line-height: 1.6;
    color: #333;
}

.static-body :deep(p) { margin: 0 0 0.75rem 0; }
.static-body :deep(ul), .static-body :deep(ol) { margin: 0 0 0.75rem 1.25rem; }
.static-body :deep(h2) { font-size: 1.15rem; margin: 1rem 0 0.5rem 0; }
.static-empty { color: #666; margin: 0; }
</style>
