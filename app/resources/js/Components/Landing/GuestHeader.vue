<template>
    <header class="guest-header" :class="{ 'guest-header--dark': variant === 'dark' }">
        <div class="guest-header-inner">
            <Link :href="route('landing')" class="guest-logo">Matau Kaune</Link>
            <nav class="guest-nav">
                <Link :href="route('reports')" class="guest-link" :class="{ 'guest-link--active': activeLink === 'reports' }">Pranešimai</Link>
                <Link :href="route('public.statistics')" class="guest-link" :class="{ 'guest-link--active': activeLink === 'statistics' }">Statistika</Link>
                <a :href="route('login')" class="guest-link guest-link--btn">Prisijungti</a>
            </nav>
        </div>
        <div v-if="variant !== 'dark'" class="guest-header-line" />
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
.guest-header {
    background: #fff;
    flex-shrink: 0;
    box-sizing: border-box;
    width: 100%;
}

.guest-header--dark {
    background: #1946A0;
}

.guest-header--dark .guest-header-line {
    display: none;
}

.guest-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 80px;
    margin: 0 auto;
    max-width: 1200px;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .guest-header-inner {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.guest-logo {
    font-size: 1.25rem;
    font-weight: 700;
    color: #000;
    text-decoration: none;
}

.guest-header--dark .guest-logo {
    color: #fff;
}

.guest-nav {
    display: flex;
    align-items: center;
    gap: 2.5rem;
}

.guest-link {
    color: #000;
    text-decoration: none;
    font-size: 1rem;
}

.guest-header--dark .guest-link:not(.guest-link--btn) {
    color: #fff;
}

.guest-link--active {
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #1946A0;
    padding-bottom: 2px;
}

.guest-header--dark .guest-link--active {
    color: #fff;
    border-bottom-color: #fff;
}

.guest-link--btn {
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    background: #1946A0;
    color: #fff !important;
    font-weight: 500;
}

.guest-header--dark .guest-link--btn {
    background: #fff;
    color: #333 !important;
    border: 1px solid #ccc;
}

.guest-link--btn:hover {
    opacity: 0.9;
}

.guest-header-line {
    height: 2px;
    background: #1946A0;
    width: 100%;
}
</style>
