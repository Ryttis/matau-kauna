<script setup>
import { Head } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
</script>

<template>
    <Head title="Kontaktai – Kauno problemos" />
    <div class="landing static-page">
        <LandingHeader />
        <main class="static-main">
            <div class="static-container">
                <h1 class="static-title">Kontaktai</h1>
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
    margin: 0;
}
</style>
