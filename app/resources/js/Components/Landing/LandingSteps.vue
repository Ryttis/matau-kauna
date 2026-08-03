<template>
    <section class="landing-section">
        <div class="landing-container">
            <h2 class="landing-section-title">Kaip tai veikia?</h2>
            <p class="landing-section-desc">
                Keturi paprasti žingsniai – nuo pastebėjimo iki sprendimo.
            </p>
            <div class="landing-steps">
                <div class="landing-step">
                    <span class="landing-step-num">1</span>
                    <h3 class="landing-step-title">Pasirink kategoriją</h3>
                    <p class="landing-step-desc">Duobė, šviesos, želdynai, šiukšlės, vandentiekis ir dar daugiau – surask tinkamą tipą.</p>
                </div>
                <div class="landing-step">
                    <span class="landing-step-num">2</span>
                    <h3 class="landing-step-title">Aprašyk situaciją</h3>
                    <p class="landing-step-desc">Keliais žodžiais paaiškink, ką matei. Galima pridėti nuotrauką – tai labai padeda.</p>
                </div>
                <div class="landing-step">
                    <span class="landing-step-num">3</span>
                    <h3 class="landing-step-title">Pažymėk vietą</h3>
                    <p class="landing-step-desc">Spustelėk žemėlapyje arba įvesk adresą. Tikslumas pagreitina sprendimą.</p>
                </div>
                <div class="landing-step">
                    <span class="landing-step-num">4</span>
                    <h3 class="landing-step-title">Sek eigą</h3>
                    <p class="landing-step-desc">Gausite pranešimus, kai problema bus priimta ir išnagrinėta.</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-section {
    background: #F5F5F5;
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
        gap: 16px;
    }
}

.landing-step {
    background: #fff;
    border: none;
    border-radius: 15px;
    padding: 28px 20px 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 16px;
    box-sizing: border-box;
}

.landing-step-num {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--lp-steps-accent);
    color: #fff;
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
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
