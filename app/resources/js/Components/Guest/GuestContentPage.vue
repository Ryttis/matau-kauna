<script setup>
import { Head } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'

defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' }
})
</script>

<template>
    <Head :title="title + ' – Kauno problemos'" />
    <div class="landing guest-content-page">
        <GuestHeader />
        <main class="guest-content-main">
            <div class="guest-content-container">
                <h1 class="guest-content-title">{{ title }}</h1>
                <div v-if="body" class="guest-content-body content-html" v-html="body"></div>
                <p v-else class="guest-content-empty">Turinys dar neįkeltas.</p>
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
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.landing :deep(.landing-container),
.landing :deep(.landing-container--wide) {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
}

.guest-content-main {
    flex: 1;
    padding: 2rem 0 3rem;
}

.guest-content-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.guest-content-title {
    font-size: clamp(1.5rem, 4vw, 1.75rem);
    font-weight: 700;
    color: #000;
    margin: 0 0 1rem 0;
}

.guest-content-body {
    line-height: 1.6;
    color: #333;
}

.guest-content-body :deep(p) { margin: 0 0 0.75rem 0; }
.guest-content-body :deep(ul), .guest-content-body :deep(ol) { margin: 0 0 0.75rem 1.25rem; }
.guest-content-body :deep(h2) { font-size: 1.15rem; margin: 1rem 0 0.5rem 0; }
.guest-content-empty { color: #666; margin: 0; }
</style>
