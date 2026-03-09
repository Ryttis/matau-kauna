<template>
    <main class="landing-hero">
        <div class="landing-container landing-container--wide landing-hero-grid">
            <div class="landing-hero-media">
                <img
                    src="/images/index.png"
                    alt="Miesto gatvės iliustracija"
                    class="landing-hero-image"
                />
            </div>
            <div class="landing-hero-content">
                <h1 class="landing-hero-title">Praneškite apie problemą mieste</h1>
                <p class="landing-hero-desc">
                    Pastebėjote problemą Kaune? Užregistruokite ją - mes perduosime<br>
                    atsakingoms tarnyboms ir informuosime apie sprendimą.
                </p>
                <a :href="route('reports')" class="landing-cta">
                    <span class="landing-cta-icon" aria-hidden="true">+</span>
                    <span>Nauja problema</span>
                </a>
            </div>
        </div>
    </main>
</template>

<style scoped>
.landing-hero {
    flex: 0 0 auto;
    min-height: calc(100vh - 72px);
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem 0;
}

.landing-hero-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    align-items: center;
    width: 100%;
}

@media (min-width: 900px) {
    .landing-hero-grid {
        grid-template-columns: 1.2fr 0.9fr;
    }
}

.landing-hero-media {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

@media (min-width: 900px) {
    .landing-hero-media {
        justify-content: flex-start;
    }
}

.landing-hero-image {
    max-width: 100%;
    width: auto;
    height: auto;
    max-height: 50vh;
    object-fit: contain;
    display: block;
}

@media (min-width: 900px) {
    .landing-hero-image {
        max-height: 62vh;
        height: 62vh;
        object-position: left center;
    }
}

.landing-hero-content {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 2rem;
}

.landing-hero-title {
    font-family: var(--lp-font);
    font-size: clamp(1.75rem, 5vw, 48px);
    font-weight: 400;
    line-height: 1.5;
    letter-spacing: -0.011em;
    color: var(--lp-text);
    margin: 0;
}

@media (min-width: 900px) {
    .landing-hero-title {
        white-space: nowrap;
    }
}

.landing-hero-desc {
    font-family: var(--lp-font);
    font-size: 18px;
    line-height: 1.5;
    letter-spacing: -0.011em;
    color: var(--lp-text-muted);
    margin: 0;
    max-width: 620px;
    text-align: center;
}

.landing-cta {
    font-family: var(--lp-font);
    font-size: 18px;
    font-weight: 400;
    line-height: 1.5;
    letter-spacing: -0.011em;
    color: #fff;
    background: var(--lp-primary);
    border: none;
    border-radius: 9999px;
    padding: 14px 28px;
    min-height: 48px;
    cursor: pointer;
    box-sizing: border-box;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.landing-cta-icon {
    font-size: 22px;
    font-weight: 300;
    line-height: 1;
}
</style>
