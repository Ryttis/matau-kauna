<template>
    <header class="guest-header-root">
        <div class="guest-header-fixed" :class="{ 'guest-header-fixed--dark': variant === 'dark' }">
            <div class="guest-header-inner">
                <Link :href="route('landing')" class="guest-logo">Matau Kauną</Link>
                <nav class="guest-nav">
                    <Link
                        :href="route('reports')"
                        class="guest-link"
                        :class="{ 'guest-link--active': activeLink === 'reports' }"
                    >
                        Pranešimai
                    </Link>
                    <Link
                        :href="route('public.statistics')"
                        class="guest-link"
                        :class="{ 'guest-link--active': activeLink === 'statistics' }"
                    >
                        Statistika
                    </Link>
                    <a :href="route('login')" class="guest-link guest-link--btn">Prisijungti</a>
                </nav>
            </div>
        </div>
        <div class="guest-header-spacer" aria-hidden="true" />
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
.guest-header-root {
    width: 100%;
}

.guest-header-fixed {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: #fff;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
}

.guest-header-fixed--dark {
    background: #1946a0;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
}

.guest-header-spacer {
    height: 80px;
    flex-shrink: 0;
    width: 100%;
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
    font-style: italic;
    color: #000;
    text-decoration: none;
}

.guest-header-fixed--dark .guest-logo {
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

.guest-header-fixed--dark .guest-link:not(.guest-link--btn) {
    color: #fff;
}

.guest-link--active {
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #1946a0;
    padding-bottom: 2px;
}

.guest-header-fixed--dark .guest-link--active {
    color: #fff;
    border-bottom-color: #fff;
}

.guest-link--btn {
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    background: #1946a0;
    color: #fff !important;
    font-weight: 500;
}

.guest-header-fixed--dark .guest-link--btn {
    background: #fff;
    color: #333 !important;
    border: 1px solid #ccc;
}

.guest-link--btn:hover {
    opacity: 0.9;
}

.guest-header-line {
    height: 2px;
    background: #1946a0;
    width: 100%;
}
</style>
