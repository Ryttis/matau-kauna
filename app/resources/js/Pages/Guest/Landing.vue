<script setup>
import { Head } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingHero from '@/Components/Landing/LandingHero.vue'
import LandingSteps from '@/Components/Landing/LandingSteps.vue'
import LandingMap from '@/Components/Landing/LandingMap.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'

defineProps({
    problems: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Kauno problemos – Praneškite apie problemą mieste" />

    <div class="landing">
        <LandingHeader variant="dark" />
        <LandingHero />
        <div class="landing-hero-spacer" aria-hidden="true" />
        <LandingSteps />
        <LandingMap :problems="problems ?? []" />
        <LandingFooter />
    </div>
</template>

<style scoped>
.landing {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--lp-bg);
    font-family: var(--lp-font);
    --lp-font: var(--font-family-base, 'Open Sans', sans-serif);
    --lp-bg: #fff;
    --lp-text: #000;
    --lp-text-muted: #333;
    --lp-primary: var(--color-primary, #1946A0);
    --lp-border: #b0c4de;
    --lp-section-bg: #f0f0f0;
    --lp-step-border: #e0e0e0;
    --lp-step-divider: #b8d4c8;
    --lp-footer-bg: var(--color-primary, #1946A0);
    --lp-hero-accent: #2F6FEF;
    --lp-steps-bg: #F8F9FA;
    --lp-steps-card-bg: #EBF1F7;
    --lp-steps-accent: #2F6FEF;
    --lp-steps-title: #212529;
    --lp-steps-desc: #6C757D;
}

.landing-hero-spacer {
    flex: 0 0 auto;
    min-height: 10px;
    height: 10px;
    background: var(--lp-bg);
}

.landing :deep(img) {
    -webkit-user-select: none;
    user-select: none;
}

.landing :deep(.landing-container) {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .landing :deep(.landing-container) {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.landing :deep(.landing-container--wide) {
    max-width: 1400px;
}

@media (min-width: 768px) {
    .landing :deep(.landing-container--wide) {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
</style>
