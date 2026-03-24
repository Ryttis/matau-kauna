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
                <div class="landing-step">
                    <h3 class="landing-step-title">Kokia eiga?</h3>
                    <p class="landing-step-desc">Apie problemos nagrinėjimą būsite informuojamas.</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-section {
    background: var(--lp-steps-bg);
    padding: 3rem 0 3rem;
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
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 32px;
    font-weight: 700;
    line-height: 1.5;
    letter-spacing: -0.011em;
    color: var(--lp-steps-title);
    margin: 0 0 0.75rem;
    text-align: center;
}

.landing-section-desc {
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.5;
    letter-spacing: -0.011em;
    color: var(--lp-steps-accent);
    margin: 0 0 2.5rem;
    text-align: center;
}

.landing-steps {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 768px) {
    .landing-steps {
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
    }
}

.landing-step {
    background: var(--lp-steps-card-bg);
    border: 1px solid var(--lp-steps-accent);
    border-radius: 15px;
    padding: 16px 16px 32px;
    display: flex;
    flex-direction: column;
    gap: 24px;
    box-sizing: border-box;
}

.landing-step-title {
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--lp-steps-title);
    margin: 0;
}

.landing-step-desc {
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 0.9375rem;
    color: var(--lp-steps-desc);
    margin: 0;
    line-height: 1.55;
}

@media (max-width: 767px) {
    .landing-section-title {
        font-size: clamp(1.5rem, 5.5vw, 1.85rem);
        line-height: 1.25;
    }

    .landing-section-desc {
        font-size: clamp(1.05rem, 3.8vw, 1.15rem);
        line-height: 1.45;
        margin-bottom: 2rem;
    }

    .landing-step-title {
        font-size: 1.0625rem;
    }

    .landing-step-desc {
        font-size: 1rem;
    }
}
</style>
