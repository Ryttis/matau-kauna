<template>
    <header class="landing-header">
        <div class="landing-header-inner">
            <Link :href="route('landing')" class="landing-logo">Kauno problemos</Link>
            <nav class="landing-nav">
                <Link :href="route('reports')" class="landing-link" :class="{ 'landing-link--active': activeLink === 'reports' }">Pranešimai</Link>
                <Link :href="route('public.statistics')" class="landing-link" :class="{ 'landing-link--active': activeLink === 'statistics' }">Statistika</Link>
                <a :href="route('login')" class="landing-link">Prisijungti</a>
            </nav>
        </div>
        <div class="landing-header-line" />
    </header>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    activeLink: { type: String, default: '' },
})
</script>

<style scoped>
.landing-header {
    background: #fff;
    flex-shrink: 0;
    box-sizing: border-box;
}

.landing-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 72px;
    margin: 0 auto;
    max-width: 1200px;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .landing-header-inner {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.landing-logo {
    font-size: 1.25rem;
    font-weight: 700;
    color: #000;
    text-decoration: none;
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

.landing-link--active {
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #1946A0;
    padding-bottom: 2px;
}

.landing-header-line {
    height: 2px;
    background: #1946A0;
    width: 100%;
}
</style>
