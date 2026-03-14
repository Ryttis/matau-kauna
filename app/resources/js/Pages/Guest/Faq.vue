<script setup>
import { Head } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'

defineProps({
    title: { type: String, default: 'DUK' },
    body: { type: String, default: '' }
})
</script>

<template>
    <Head :title="title + ' – Kauno problemos'" />
    <div class="landing faq-page">
        <GuestHeader />
        <main class="faq-main">
            <div class="faq-container">
                <h1 class="faq-title">{{ title }}</h1>
                <div v-if="body" class="faq-body content-html" v-html="body"></div>
                <p v-else class="faq-empty">Turinys dar neįkeltas.</p>
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

.landing :deep(.landing-container),
.landing :deep(.landing-container--wide) {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
}

.faq-main {
    flex: 1;
    padding: 2rem 0 3rem;
}

.faq-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.faq-title {
    font-size: clamp(1.5rem, 4vw, 1.75rem);
    font-weight: 700;
    color: #000;
    margin: 0 0 1rem 0;
}

.faq-body {
    line-height: 1.6;
    color: #333;
}

.faq-body :deep(p) { margin: 0 0 0.75rem 0; }
.faq-body :deep(ul), .faq-body :deep(ol) { margin: 0 0 0.75rem 1.25rem; }
.faq-body :deep(h2) { font-size: 1.15rem; margin: 1rem 0 0.5rem 0; }
.faq-empty { color: #666; margin: 0; }
</style>
