<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import GuestHeader from '@/Components/Landing/GuestHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const resend = () => {
    router.post(route('verification.send'));
};
</script>

<template>
    <div class="verify-email-page">
        <Head title="Patvirtinkite el. paštą" />

        <GuestHeader />

        <main class="verify-email-main">
            <div class="verify-email-card">
                <h1 class="verify-email-title">Patvirtinkite el. paštą</h1>
                <p class="verify-email-text">
                    Prieš tęsdami, patikrinkite el. paštą ir spustelėkite patvirtinimo nuorodą.
                </p>
                <p v-if="status" class="verify-email-status">
                    {{ status }}
                </p>
                <div class="verify-email-actions">
                    <AppButton
                        text="Siųsti patvirtinimo nuorodą dar kartą"
                        :as-button="true"
                        @click="resend"
                    />
                </div>
            </div>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.verify-email-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: 'Open Sans', sans-serif;
}

.verify-email-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.verify-email-card {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    font-size: 18px;
}

.verify-email-title {
    margin: 0 0 1rem 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.verify-email-text {
    margin: 0 0 1rem 0;
    color: #666;
    line-height: 1.5;
}

.verify-email-status {
    margin: 0 0 1rem 0;
    color: #0a0;
    font-size: 14px;
}

.verify-email-actions :deep(.app-button) {
    width: 100%;
    height: var(--button-height, 45px);
    min-height: var(--button-height, 45px);
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
