<template>
    <header class="landing-header" :class="{ 'landing-header--dark': variant === 'dark' }">
        <template v-if="variant === 'dark'">
            <div class="landing-header-fixed-bar">
                <div class="landing-header-inner landing-header-inner--nav">
                    <Link :href="route('landing')" class="landing-logo">Matau Kauną</Link>
                    <nav class="landing-nav">
                        <Link
                            :href="route('reports')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'reports' }"
                        >
                            Pranešimai
                        </Link>
                        <Link
                            :href="route('public.statistics')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'statistics' }"
                        >
                            Statistika
                        </Link>
                        <a :href="route('login')" class="landing-link landing-link--btn">Prisijungti</a>
                    </nav>
                </div>
            </div>
            <div class="landing-header-spacer" aria-hidden="true" />
            <div class="landing-header-blue-fill" aria-hidden="true" />
        </template>
        <template v-else>
            <div class="landing-header-fixed-bar landing-header-fixed-bar--light">
                <div class="landing-header-inner landing-header-inner--nav landing-header-inner--light">
                    <Link :href="route('landing')" class="landing-logo">Matau Kauną</Link>
                    <nav class="landing-nav">
                        <Link
                            :href="route('reports')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'reports' }"
                        >
                            Pranešimai
                        </Link>
                        <Link
                            :href="route('public.statistics')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'statistics' }"
                        >
                            Statistika
                        </Link>
                        <a :href="route('login')" class="landing-link landing-link--btn">Prisijungti</a>
                    </nav>
                </div>
            </div>
            <div class="landing-header-spacer" aria-hidden="true" />
            <div class="landing-header-line" />
        </template>
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
    flex-shrink: 0;
    box-sizing: border-box;
    width: 100%;
}

.landing-header--dark {
    background: transparent;
}

.landing-header-fixed-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: var(--lp-primary, #1946a0);
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
}

.landing-header-fixed-bar--light {
    background: #fff;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
}

.landing-header-spacer {
    height: 80px;
    width: 100%;
    flex-shrink: 0;
}

.landing-header-blue-fill {
    width: 100%;
    height: 213px;
    background: var(--lp-primary, #1946a0);
    flex-shrink: 0;
}

@media (max-height: 900px) {
    .landing-header-blue-fill {
        height: 180px;
    }
}

.landing-header-inner {
    margin: 0 auto;
    max-width: 1440px;
    width: 100%;
    box-sizing: border-box;
}

.landing-header-inner--nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 80px;
    padding: 1rem 1rem;
}

.landing-header-inner--light .landing-logo {
    color: #000;
}

@media (min-width: 768px) {
    .landing-header-inner--nav {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.landing-logo {
    font-size: 1.25rem;
    font-weight: 700;
    font-style: italic;
    color: #fff;
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

.landing-header-fixed-bar:not(.landing-header-fixed-bar--light) .landing-link:not(.landing-link--btn) {
    color: #fff;
}

.landing-link--active {
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #1946a0;
    padding-bottom: 2px;
}

.landing-header-fixed-bar:not(.landing-header-fixed-bar--light) .landing-link--active {
    color: #fff;
    border-bottom-color: #fff;
}

.landing-header-fixed-bar--light .landing-link--active {
    color: #333;
    border-bottom-color: #1946a0;
}

.landing-link--btn {
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    background: #1946a0;
    color: #fff !important;
    font-weight: 500;
}

.landing-header-fixed-bar:not(.landing-header-fixed-bar--light) .landing-link--btn {
    background: #fff;
    color: #333 !important;
    border: 1px solid #ccc;
}

.landing-link--btn:hover {
    opacity: 0.9;
}

.landing-header-line {
    height: 2px;
    background: #1946a0;
    width: 100%;
}
</style>
