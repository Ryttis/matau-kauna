<script setup>
import GuestHeader from "@/Components/Landing/GuestHeader.vue";
import LandingFooter from "@/Components/Landing/LandingFooter.vue";
import { Head } from "@inertiajs/vue3";
import { onMounted } from "vue";

const props = defineProps({
    shouldOpenMobileApp: {
        type: Boolean,
        default: false,
    },
    mobileAppDeepLink: {
        type: String,
        default: "mataukauna://home",
    },
});

function openMobileApp() {
    if (!props.mobileAppDeepLink) {
        return;
    }

    window.location.href = props.mobileAppDeepLink;
}

onMounted(() => {
    if (props.shouldOpenMobileApp) {
        openMobileApp();
    }
});
</script>

<template>
    <div class="verify-success-page">
        <Head title="El. paštas patvirtintas" />

        <GuestHeader />

        <main class="verify-success-main">
            <div class="verify-success-card">
                <div class="verify-success-icon" aria-hidden="true">
                    <svg
                        viewBox="0 0 24 24"
                        width="56"
                        height="56"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.4"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                </div>
                <h1 class="verify-success-title">El. paštas patvirtintas</h1>
                <p class="verify-success-text">
                    Galite uždaryti šį langą - ankstesnėje kortelėje paskyra
                    atsinaujins automatiškai.
                </p>
                <button
                    v-if="shouldOpenMobileApp"
                    type="button"
                    class="verify-success-open-app"
                    @click="openMobileApp"
                >
                    Atidaryti programėlę
                </button>
            </div>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.verify-success-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: var(--font-family-base, "Open Sans", sans-serif);
}

.verify-success-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.verify-success-card {
    width: 100%;
    max-width: 440px;
    text-align: center;
}

.verify-success-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 88px;
    height: 88px;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    margin-bottom: 1.25rem;
}

.verify-success-title {
    margin: 0 0 0.75rem 0;
    font-size: 24px;
    font-weight: 600;
    color: #1f2937;
}

.verify-success-text {
    margin: 0;
    color: #4b5563;
    font-size: 16px;
    line-height: 1.55;
}

.verify-success-open-app {
    margin-top: 1.25rem;
    min-height: 44px;
    padding: 0 18px;
    border: none;
    border-radius: 6px;
    background: var(--color-primary, #1946a0);
    color: #fff;
    font-family: inherit;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.verify-success-open-app:hover,
.verify-success-open-app:focus-visible {
    background: var(--color-primary-dark, #12377f);
}
</style>
