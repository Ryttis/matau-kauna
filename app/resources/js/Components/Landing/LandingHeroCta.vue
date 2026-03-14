<template>
    <div class="landing-hero-cta">
        <Link :href="route('reporter.report')" class="landing-hero-cta__btn">
            + Noriu pranešti apie problemą
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<style scoped>
.landing-hero-cta {
    width: 100%;
    max-width: 100%;
    height: 100px;
    margin: 0 auto;
    margin-top: 2rem;
    padding: 0 1.5rem;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(25, 70, 160, 0.15);
    border: 1px solid var(--lp-primary);
    border-radius: 40px;
}

@media (min-width: 1280px) {
    .landing-hero-cta {
        margin-left: 3.75rem;
        margin-right: 5rem;
        width: calc(100% - 3.75rem - 5rem);
        max-width: calc(100% - 3.75rem - 5rem);
    }
}

@media (min-width: 1440px) {
    .landing-hero-cta {
        margin-left: auto;
        margin-right: auto;
        margin-top: 0;
        width: 100%;
        max-width: 1200px;
    }
}

@media (min-width: 900px) and (max-width: 1279px) {
    .landing-hero-cta {
        margin-left: 3.75rem;
        margin-right: 5rem;
        width: calc(100% - 3.75rem - 5rem);
        max-width: calc(100% - 3.75rem - 5rem);
    }
}

@media (min-width: 900px) and (max-height: 960px) {
    .landing-hero-cta {
        margin-top: 0;
    }
}


.landing-hero-cta__btn {
    display: inline-block;
    padding: 12px 36px;
    background: var(--lp-hero-accent);
    color: #fff;
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 20px;
    font-weight: 400;
    line-height: 1;
    letter-spacing: 0;
    text-decoration: none;
    border-radius: 15px;
    border: none;
    cursor: pointer;
    transition: opacity 0.2s;
    box-sizing: border-box;
}

.landing-hero-cta__btn:hover {
    opacity: 0.9;
}
</style>
