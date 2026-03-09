<template>
    <CookieConsentBanner />
    <footer class="landing-footer">
        <div class="landing-footer-inner">
            <Link :href="route('landing')" class="landing-footer-logo">Kauno problemos</Link>
            <div class="landing-footer-right">
                <nav class="landing-footer-nav">
                    <Link :href="route('faq')" class="landing-footer-link">DUK</Link>
                    <Link :href="route('privacy')" class="landing-footer-link">Privatumo taisyklės</Link>
                    <Link :href="route('cookies')" class="landing-footer-link">Slapukai</Link>
                    <Link :href="route('contacts')" class="landing-footer-link">Kontaktai</Link>
                    <Link :href="route('help')" class="landing-footer-link">
                        <img :src="ICON_QUESTION" alt="" class="landing-footer-icon" width="18" height="18" />
                        Pagalba
                    </Link>
                </nav>
                <p class="landing-footer-copy">© 2026 Kauno miesto savivaldybė</p>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import CookieConsentBanner from '@/Components/Landing/CookieConsentBanner.vue'
import { ICON_QUESTION } from '@/constants/icons'
</script>

<style scoped>
.landing-footer {
    background: #1946A0;
    color: #fff;
    flex-shrink: 0;
    min-height: 154px;
    display: flex;
    align-items: center;
    padding: 1.5rem 0;
}
.landing-footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 1.5rem;
    width: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.landing-footer-logo {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    text-decoration: none;
}
.landing-footer-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.75rem;
}
.landing-footer-nav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 1.25rem 1.5rem;
}
.landing-footer-link {
    color: #fff;
    text-decoration: none;
    font-size: 1rem;
}
.landing-footer-link:hover {
    text-decoration: underline;
}
.landing-footer-icon {
    display: inline-block;
    margin-right: 0.35rem;
    vertical-align: middle;
    width: 18px;
    height: 18px;
    object-fit: contain;
}
.landing-footer-copy {
    margin: 0;
    font-size: 0.875rem;
    color: #fff;
    text-align: right;
}
</style>
