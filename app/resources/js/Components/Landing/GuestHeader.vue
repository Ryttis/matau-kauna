<template>
    <header class="guest-header-root">
        <div class="guest-header-fixed" :class="{ 'guest-header-fixed--dark': variant === 'dark' }">
            <div class="guest-header-inner">
                <Link :href="brandHomeHref" class="guest-logo" aria-label="Matau Kauną">
                    <img
                        src="/images/matau_kaune.png"
                        alt=""
                        class="guest-logo-image"
                        :class="{ 'guest-logo-image--on-light': variant !== 'dark' }"
                    />
                </Link>
                <button
                    type="button"
                    class="guest-menu-btn"
                    :class="{ 'guest-menu-btn--dark': variant === 'dark' }"
                    aria-label="Meniu"
                    :aria-expanded="navOpen"
                    @click="navOpen = !navOpen"
                >
                    <span class="guest-menu-icon" aria-hidden="true" />
                </button>
                <nav class="guest-nav" :class="{ 'guest-nav--open': navOpen }">
                    <Link
                        :href="route('reports')"
                        class="guest-link"
                        :class="{ 'guest-link--active': activeLink === 'reports' }"
                        @click="navOpen = false"
                    >
                        Pranešimai
                    </Link>
                    <Link
                        :href="route('public.statistics')"
                        class="guest-link"
                        :class="{ 'guest-link--active': activeLink === 'statistics' }"
                        @click="navOpen = false"
                    >
                        Statistika
                    </Link>
                    <Link
                        :href="user ? route('dashboard') : route('login')"
                        class="guest-link guest-link--btn"
                        @click="navOpen = false"
                    >
                        {{ user ? 'Skydelis' : 'Prisijungti' }}
                    </Link>
                </nav>
            </div>
        </div>
        <div class="guest-header-spacer" aria-hidden="true" />
        <div v-if="variant !== 'dark'" class="guest-header-line" />
    </header>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAuth } from '@/composables/useAuth'
import { useBrandHomeHref } from '@/composables/useBrandHomeHref'

const brandHomeHref = useBrandHomeHref()
const { user } = useAuth()

defineProps({
    activeLink: { type: String, default: '' },
    variant: { type: String, default: 'dark' },
})

const navOpen = ref(false)
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
    height: var(--app-header-height, 66px);
    flex-shrink: 0;
    width: 100%;
}

.guest-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: var(--app-header-height, 66px);
    height: var(--app-header-height, 66px);
    margin: 0 auto;
    max-width: 1200px;
    width: 100%;
    padding: 0 1rem;
    box-sizing: border-box;
    position: relative;
}

@media (min-width: 768px) {
    .guest-header-inner {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.guest-logo {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    min-width: 0;
}

.guest-logo:hover {
    opacity: 0.9;
}

.guest-logo-image {
    display: block;
    height: 20px;
    width: auto;
    max-width: min(160px, 48vw);
}

.guest-logo-image--on-light {
    filter: brightness(0);
}

@media (min-width: 1024px) {
    .guest-logo-image {
        height: 22px;
        max-width: 200px;
    }
}

.guest-menu-btn {
    display: none;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.06);
    cursor: pointer;
    padding: 0;
}

.guest-menu-btn--dark {
    background: rgba(255, 255, 255, 0.2);
}

.guest-menu-icon,
.guest-menu-icon::before,
.guest-menu-icon::after {
    display: block;
    width: 20px;
    height: 2px;
    background: #000;
    border-radius: 1px;
    position: relative;
}

.guest-header-fixed--dark .guest-menu-icon,
.guest-header-fixed--dark .guest-menu-icon::before,
.guest-header-fixed--dark .guest-menu-icon::after {
    background: #fff;
}

.guest-menu-icon::before,
.guest-menu-icon::after {
    content: '';
    position: absolute;
    left: 0;
}

.guest-menu-icon::before {
    top: -7px;
}

.guest-menu-icon::after {
    top: 7px;
}

.guest-nav {
    display: flex;
    align-items: center;
    gap: 2.5rem;
}

@media (max-width: 767px) {
    .guest-menu-btn {
        display: inline-flex;
    }

    .guest-nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        padding: 8px 0 16px;
        background: #fff;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        z-index: 1001;
    }

    .guest-header-fixed--dark .guest-nav {
        background: #1946a0;
    }

    .guest-nav--open {
        display: flex;
    }

    .guest-link {
        padding: 14px 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .guest-header-fixed--dark .guest-link:not(.guest-link--btn) {
        border-bottom-color: rgba(255, 255, 255, 0.12);
    }

    .guest-link--btn {
        margin: 12px 1rem 0;
        text-align: center;
    }
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
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 6px 16px;
    border-radius: 10px;
    background: #1946a0;
    color: #fff !important;
    font-weight: 500;
    line-height: 22px;
    box-sizing: border-box;
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
