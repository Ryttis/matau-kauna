<template>
    <header class="landing-header" :class="{ 'landing-header--dark': variant === 'dark' }">
        <div class="landing-header-inner">
            <Link :href="route('landing')" class="landing-logo">Matau Kauną</Link>
            <nav class="landing-nav">
                <Link :href="route('reports')" class="landing-link" :class="{ 'landing-link--active': activeLink === 'reports' }">Pranešimai</Link>
                <Link :href="route('public.statistics')" class="landing-link" :class="{ 'landing-link--active': activeLink === 'statistics' }">Statistika</Link>
                <a :href="route('login')" class="landing-link landing-link--btn">Prisijungti</a>
            </nav>
        </div>
        <div v-if="variant !== 'dark'" class="landing-header-line" />
    </header>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    activeLink: { type: String, default: '' },
    variant: { type: String, default: '' },
})
</script>

<style scoped>
.landing-header {
    background: #fff;
    flex-shrink: 0;
    box-sizing: border-box;
    width: 100%;
}

.landing-header--dark {
    background: var(--lp-primary);
    height: 293px;
    min-height: 293px;
}

.landing-header--dark .landing-header-line {
    display: none;
}

.landing-header-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    height: 293px;
    margin: 0 auto;
    max-width: 1440px;
    width: 100%;
    padding: 1rem 1rem 0;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .landing-header-inner {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

@media (max-height: 900px) {
    .landing-header--dark {
        height: 260px;
        min-height: 260px;
    }
    .landing-header-inner {
        height: 260px;
    }
}

.landing-logo {
    font-size: 1.25rem;
    font-weight: 700;
    font-style: italic;
    color: #000;
    text-decoration: none;
}

.landing-header--dark .landing-logo {
    color: #fff;
}

.landing-nav {
    display: flex;
    align-items: center;
    gap: 2.5rem;
}

.landing-link {
    color: #000;
    text-decoration: none;
    font-size: 1rem;
}

.landing-header--dark .landing-link:not(.landing-link--btn) {
    color: #fff;
}

.landing-link--active {
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #1946A0;
    padding-bottom: 2px;
}

.landing-header--dark .landing-link--active {
    color: #fff;
    border-bottom-color: #fff;
}

.landing-link--btn {
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    background: #1946A0;
    color: #fff !important;
    font-weight: 500;
}

.landing-header--dark .landing-link--btn {
    background: #fff;
    color: #333 !important;
    border: 1px solid #ccc;
}

.landing-link--btn:hover {
    opacity: 0.9;
}

.landing-header-line {
    height: 2px;
    background: #1946A0;
    width: 100%;
}
</style>
