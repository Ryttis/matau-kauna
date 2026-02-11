<script setup>
import { Head } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
</script>

<template>
    <Head title="DUK – Kauno problemos" />
    <div class="landing faq-page">
        <LandingHeader />
        <main class="faq-main">
            <div class="faq-container">
                <h1 class="faq-title">DUK</h1>
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
    margin: 0;
}
</style>
