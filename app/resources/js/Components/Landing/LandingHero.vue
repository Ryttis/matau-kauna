<template>
    <main class="landing-hero">
        <div class="landing-container landing-container--wide landing-hero-inner">
            <div class="landing-hero-top">
                <LandingHeroDots />
                <LandingHeroBlock />
            </div>
            <LandingHeroCta />
        </div>
    </main>
</template>

<script setup>
import LandingHeroDots from './LandingHeroDots.vue';
import LandingHeroBlock from './LandingHeroBlock.vue';
import LandingHeroCta from './LandingHeroCta.vue';
</script>

<style scoped>
.landing-hero {
    background: #fff;
    flex: 0 0 auto;
    width: 100%;
}

.landing-hero-inner {
    width: 100%;
    padding-left: 1rem;
    padding-right: 1rem;
    box-sizing: border-box;
}

.landing-hero-top {
    position: relative;
}

@media (min-width: 900px) {
    .landing-hero-top {
        margin-top: 1.25rem;
        padding-bottom: 5rem;
    }
}

@media (min-width: 900px) and (max-height: 900px) {
    .landing-hero-top {
        margin-top: 1.5rem;
    }
}

@media (min-width: 768px) {
    .landing-hero-inner {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

@media (min-width: 900px) and (max-height: 900px) {
    .landing-hero-inner {
        transform: scale(0.88);
        transform-origin: top center;
        margin-bottom: -84px;
    }
}

@media (min-width: 900px) and (max-height: 960px) and (min-height: 901px) {
    .landing-hero-inner {
        transform: scale(0.92);
        transform-origin: top center;
        margin-bottom: -56px;
    }
}

</style>
