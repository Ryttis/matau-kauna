<template>
    <div class="landing-hero-block">
        <div class="landing-hero-phone-wrap">
            <img
                src="/images/phone.png"
                alt="Programėlė žemėlapyje"
                class="landing-hero-phone"
            />
        </div>
        <div class="landing-hero-taglines-wrap" aria-hidden="true">
            <span class="landing-hero-tagline">Pažymėk ir stebėk</span>
            <span class="landing-hero-tagline">Patogu ir paprasta naudotis</span>
        </div>
        <div class="landing-hero-content">
            <div class="landing-hero-content-top">
                <h1 class="landing-hero-title">Praneškite apie problemą mieste</h1>
                <p class="landing-hero-desc">
                    Pastebėjote problemą Kaune? Užregistruokite ją – mes perduosime atsakingoms tarnyboms ir informuosime apie sprendimą.
                </p>
            </div>
            <div class="landing-hero-content-bottom">
                <div class="landing-hero-buttons">
                    <a href="https://play.google.com/store" target="_blank" rel="noopener noreferrer" class="landing-dl-btn">
                        <img src="/images/google.png" alt="Atsisiųsk Google Play" class="landing-dl-btn-img" />
                    </a>
                    <a href="https://apps.apple.com" target="_blank" rel="noopener noreferrer" class="landing-dl-btn">
                        <img src="/images/apple.png" alt="Atsisiųsk App Store" class="landing-dl-btn-img" />
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.landing-hero-block {
    position: relative;
    z-index: 2;
    overflow: visible;
    background: var(--lp-hero-accent);
    border-radius: 55px;
    padding: 2rem 1.5rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: center;
    min-height: 420px;
    width: 100%;
    max-width: 100%;
    margin: calc(88px - 293px) auto 0;
    margin-bottom: 0.5rem;
    box-sizing: border-box;
    opacity: 1;
}

@media (min-width: 1280px) {
    .landing-hero-block {
        margin-top: calc(88px - 293px);
        margin-left: 3.75rem;
        margin-right: 5rem;
        width: calc(100% - 3.75rem - 5rem);
        max-width: calc(100% - 3.75rem - 5rem);
    }
}

@media (min-width: 1440px) {
    .landing-hero-block {
        margin-left: auto;
        margin-right: auto;
        width: 100%;
        max-width: 1200px;
    }
}

@media (min-width: 900px) and (max-height: 900px) {
    .landing-hero-block {
        margin-top: calc(88px - 260px);
    }
}

@media (min-width: 900px) {
    .landing-hero-block {
        grid-template-columns: 1.1fr 1fr;
        grid-template-rows: 1fr;
        padding: 1.75rem 2.5rem 2rem;
        width: calc(100% - 3.75rem - 5rem);
        max-width: calc(100% - 3.75rem - 5rem);
        height: 563px;
        min-height: 563px;
        margin-top: calc(88px - 293px);
        margin-left: 3.75rem;
        margin-right: 5rem;
    }
    .landing-hero-phone-wrap { grid-column: 1; grid-row: 1; }
    .landing-hero-content { grid-column: 2; grid-row: 1; }
}

.landing-hero-phone-wrap {
    position: relative;
    grid-column: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 280px;
}

@media (min-width: 900px) {
    .landing-hero-phone-wrap {
        position: absolute;
        left: 20px;
        top: -10px;
        width: 640px;
        height: 640px;
        min-height: 0;
        justify-content: center;
        align-items: center;
    }
}

.landing-hero-phone {
    position: relative;
    z-index: 2;
    max-width: 200px;
    width: 55%;
    height: auto;
    display: block;
    filter: drop-shadow(3px 7px 15px rgba(0, 0, 0, 0.35));
    object-fit: contain;
}

@media (min-width: 600px) {
    .landing-hero-phone {
        max-width: 260px;
        width: 50%;
    }
}

@media (min-width: 900px) {
    .landing-hero-phone {
        width: 640px;
        height: 640px;
        max-width: 640px;
        max-height: 640px;
    }
}

.landing-hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    text-align: left;
}

.landing-hero-taglines-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    justify-content: center;
    align-items: center;
    pointer-events: none;
}

@media (min-width: 900px) {
    .landing-hero-taglines-wrap {
        position: absolute;
        left: 50%;
        top: 65%;
        transform: translate(-50%, -50%);
        z-index: 3;
    }
}

@media (max-width: 899px) {
    .landing-hero-taglines-wrap {
        order: 0;
        margin-top: 0.5rem;
    }
}

.landing-hero-tagline {
    display: inline-block;
    padding: 1rem 2rem;
    min-width: 280px;
    max-width: 320px;
    min-height: 56px;
    line-height: 1.3;
    text-align: center;
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 1.125rem;
    font-weight: 600;
    color: #fff;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 15px;
    box-sizing: border-box;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.landing-hero-content-top {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.landing-hero-content-bottom {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
}

@media (min-width: 900px) {
    .landing-hero-content {
        height: 100%;
        min-height: 100%;
        text-align: left;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0;
        padding: 0;
        margin-left: -2rem;
        margin-right: auto;
        max-width: 100%;
    }
    .landing-hero-content-top {
        align-items: flex-start;
        flex-shrink: 0;
        padding: 16px 18px 16px 110px;
        box-sizing: border-box;
        margin-top: 1rem;
    }
    .landing-hero-content-bottom {
        align-items: flex-end;
        gap: 0.75rem;
        margin-top: auto;
        width: 100%;
    }
}

.landing-hero-title {
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: clamp(1.35rem, 3.5vw, 2.5rem);
    font-weight: 700;
    line-height: 1.31;
    letter-spacing: -0.011em;
    color: #fff;
    margin: 0;
    max-width: 529px;
}

@media (min-width: 900px) {
    .landing-hero-title {
        font-size: 48px;
        line-height: 1.31;
        letter-spacing: -0.011em;
    }
}

.landing-hero-desc {
    font-family: var(--lp-font), 'Open Sans', sans-serif;
    font-size: 0.9375rem;
    font-weight: 400;
    line-height: 1.5;
    letter-spacing: -0.011em;
    color: rgba(255, 255, 255, 0.95);
    margin: 0;
    max-width: 389px;
}

@media (min-width: 900px) {
    .landing-hero-desc {
        font-size: 18px;
        line-height: 1.5;
        max-width: 389px;
        letter-spacing: -0.011em;
    }
}

.landing-hero-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
}

@media (min-width: 900px) {
    .landing-hero-buttons {
        align-items: flex-end;
        gap: 16px;
        width: 237px;
        min-height: 100px;
        margin-left: 0;
        margin-right: 2rem;
    }
}

.landing-dl-btn {
    display: inline-block;
    line-height: 0;
    text-decoration: none;
    transition: opacity 0.2s;
}

.landing-dl-btn:hover {
    opacity: 0.9;
}

.landing-dl-btn-img {
    display: block;
    height: 52px;
    width: auto;
    max-width: 200px;
    object-fit: contain;
}

@media (min-width: 900px) {
    .landing-dl-btn-img {
        width: 237px;
        height: 42px;
        max-width: 237px;
        object-fit: contain;
    }
}
</style>
