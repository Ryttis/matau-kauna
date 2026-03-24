<template>
    <header class="landing-header" :class="{ 'landing-header--dark': variant === 'dark' }">
        <template v-if="variant === 'dark'">
            <div class="landing-header-fixed-bar">
                <div class="landing-header-inner landing-header-inner--nav">
                    <Link :href="route('landing')" class="landing-logo">Matau Kauną</Link>
                    <button
                        type="button"
                        class="landing-menu-btn landing-menu-btn--on-dark"
                        aria-label="Meniu"
                        :aria-expanded="navOpen"
                        @click="navOpen = !navOpen"
                    >
                        <span class="landing-menu-icon" aria-hidden="true" />
                    </button>
                    <nav class="landing-nav" :class="{ 'landing-nav--open': navOpen }">
                        <Link
                            :href="route('reports')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'reports' }"
                            @click="navOpen = false"
                        >
                            Pranešimai
                        </Link>
                        <Link
                            :href="route('public.statistics')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'statistics' }"
                            @click="navOpen = false"
                        >
                            Statistika
                        </Link>
                        <a :href="route('login')" class="landing-link landing-link--btn" @click="navOpen = false">Prisijungti</a>
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
                    <button
                        type="button"
                        class="landing-menu-btn"
                        aria-label="Meniu"
                        :aria-expanded="navOpen"
                        @click="navOpen = !navOpen"
                    >
                        <span class="landing-menu-icon landing-menu-icon--dark" aria-hidden="true" />
                    </button>
                    <nav class="landing-nav" :class="{ 'landing-nav--open': navOpen }">
                        <Link
                            :href="route('reports')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'reports' }"
                            @click="navOpen = false"
                        >
                            Pranešimai
                        </Link>
                        <Link
                            :href="route('public.statistics')"
                            class="landing-link"
                            :class="{ 'landing-link--active': activeLink === 'statistics' }"
                            @click="navOpen = false"
                        >
                            Statistika
                        </Link>
                        <a :href="route('login')" class="landing-link landing-link--btn" @click="navOpen = false">Prisijungti</a>
                    </nav>
                </div>
            </div>
            <div class="landing-header-spacer" aria-hidden="true" />
            <div class="landing-header-line" />
        </template>
    </header>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'

defineProps({
    activeLink: { type: String, default: '' },
    variant: { type: String, default: '' },
})

const navOpen = ref(false)
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
    gap: 8px;
    min-height: 80px;
    padding: 1rem 1rem;
    position: relative;
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

.landing-menu-btn {
    display: none;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.2);
    cursor: pointer;
    padding: 0;
}

.landing-menu-btn--on-dark {
    background: rgba(255, 255, 255, 0.2);
}

.landing-header-fixed-bar--light .landing-menu-btn {
    background: rgba(0, 0, 0, 0.06);
}

.landing-menu-icon,
.landing-menu-icon::before,
.landing-menu-icon::after {
    display: block;
    width: 20px;
    height: 2px;
    background: #fff;
    border-radius: 1px;
    position: relative;
}

.landing-menu-icon--dark,
.landing-menu-icon--dark::before,
.landing-menu-icon--dark::after {
    background: #000;
}

.landing-menu-icon::before,
.landing-menu-icon::after {
    content: '';
    position: absolute;
    left: 0;
}

.landing-menu-icon::before {
    top: -7px;
}

.landing-menu-icon::after {
    top: 7px;
}

.landing-nav {
    display: flex;
    align-items: center;
    gap: 2.5rem;
}

@media (max-width: 767px) {
    .landing-logo {
        font-size: clamp(1.5rem, 4.5vw, 1.85rem);
        letter-spacing: -0.01em;
    }

    .landing-menu-btn {
        display: inline-flex;
        width: 56px;
        height: 56px;
        border-radius: 14px;
    }

    .landing-menu-icon,
    .landing-menu-icon::before,
    .landing-menu-icon::after {
        width: 24px;
        height: 2.5px;
    }

    .landing-menu-icon::before {
        top: -8px;
    }

    .landing-menu-icon::after {
        top: 8px;
    }

    .landing-nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        padding: 10px 0 16px;
        background: var(--lp-primary, #1946a0);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        z-index: 1001;
    }

    .landing-header-fixed-bar--light .landing-nav {
        background: #fff;
    }

    .landing-nav--open {
        display: flex;
    }

    .landing-link {
        padding: 16px 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        font-size: 1.05rem;
    }

    .landing-header-fixed-bar--light .landing-link {
        border-bottom-color: rgba(0, 0, 0, 0.06);
    }

    .landing-link--btn {
        margin: 12px 1rem 0;
        text-align: center;
    }

    .landing-link--active {
        border-bottom: none;
        padding-bottom: 14px;
    }
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
