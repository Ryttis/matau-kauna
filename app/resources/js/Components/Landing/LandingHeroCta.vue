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
    margin: 0 auto;
    margin-top: 2rem;
    padding: 0 1.5rem;
    box-sizing: border-box;
    display: flex;
    align-items: stretch;
    justify-content: center;
}

@media (min-width: 900px) {
    .landing-hero-cta {
        margin-left: 0;
        margin-right: 0;
        width: auto;
        max-width: none;
        padding-left: 4.5rem;
        padding-right: 4.5rem;
    }
}

@media (min-width: 900px) and (max-height: 960px) {
    .landing-hero-cta {
        margin-top: 0;
    }
}


.landing-hero-cta__btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 850px;
    min-height: 70px;
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

@media (max-width: 767px) {
    .landing-hero-cta {
        height: auto;
        min-height: 100px;
        padding: 0.75rem 1rem;
        border-radius: 28px;
    }

    .landing-hero-cta__btn {
        width: 100%;
        max-width: 560px;
        padding: 14px 20px;
        text-align: center;
        font-size: clamp(1.05rem, 4.2vw, 1.35rem);
        font-weight: 600;
        line-height: 1.15;
    }
}
</style>
