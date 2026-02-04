<template>
    <section class="landing-section">
        <div class="landing-container">
            <h2 class="landing-section-title">Pastebėjote problemą Kaune?</h2>
            <p class="landing-section-desc">
                Užregistruokite ją - mes perduosime atsakingoms tarnyboms ir informuosime apie sprendimą.
            </p>
            <div class="landing-steps">
                <div class="landing-step">
                    <h3 class="landing-step-title">Kokia problema?</h3>
                    <p class="landing-step-desc">Pasirinkite problemos kategoriją, kad galėtume ją greičiau perduoti atsakingai įstaigai.</p>
                </div>
                <div class="landing-step">
                    <h3 class="landing-step-title">Kokia situacija?</h3>
                    <p class="landing-step-desc">Trumpai aprašykite problemą. Jei galite - pridėkite nuotrauką.</p>
                </div>
                <div class="landing-step">
                    <h3 class="landing-step-title">Kur yra problema?</h3>
                    <p class="landing-step-desc">Pažymėkite problemos vietą - pasirinkite ją žemėlapyje arba įveskite adresą.</p>
                </div>
                <div class="landing-step landing-step--last">
                    <h3 class="landing-step-title">Kokia eiga?</h3>
                    <p class="landing-step-desc">Apie problemos nagrinėjimą būsite informuojamas.</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
</script>

<style scoped>
.landing-section {
    background: var(--lp-section-bg);
    padding: 5rem 0 5.5rem;
}

.landing-section .landing-container {
    padding-left: 1rem;
    padding-right: 1rem;
}

@media (min-width: 768px) {
    .landing-section .landing-container {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

.landing-section-title {
    font-family: var(--lp-font);
    font-size: clamp(1.5rem, 4vw, 1.75rem);
    font-weight: 700;
    color: var(--lp-text);
    margin: 0 0 0.75rem;
    text-align: center;
}

.landing-section-desc {
    font-family: var(--lp-font);
    font-size: 1rem;
    color: var(--lp-text-muted);
    margin: 0 0 3rem;
    text-align: center;
    line-height: 1.5;
}

.landing-steps {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0;
    border-top: 1px solid var(--lp-step-border);
    border-bottom: 1px solid var(--lp-step-border);
    background: var(--lp-section-bg);
}

@media (min-width: 768px) {
    .landing-steps {
        grid-template-columns: repeat(4, 1fr);
    }
}

.landing-step {
    padding: 2rem 1.25rem;
    border-right: none;
    border-bottom: 1px solid var(--lp-step-border);
}

.landing-step:last-child {
    border-bottom: none;
}

@media (min-width: 768px) {
    .landing-step {
        padding: 2rem 1.5rem;
        border-right: 1px solid var(--lp-step-divider);
        border-bottom: none;
    }

    .landing-step--last {
        border-right: none;
    }
}

.landing-step-title {
    font-family: var(--lp-font);
    font-size: 1rem;
    font-weight: 700;
    color: var(--lp-text);
    margin: 0 0 0.75rem;
}

.landing-step-desc {
    font-family: var(--lp-font);
    font-size: 0.9375rem;
    color: var(--lp-text-muted);
    margin: 0;
    line-height: 1.55;
}
</style>
