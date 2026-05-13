<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import SuccessModal from '@/Components/App/SuccessModal.vue';
import GuestHeader from '@/Components/Landing/GuestHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();

const showSuccessModal = ref(false);
const successModalText = ref('');
const resendError = ref('');
const COOLDOWN_SECONDS = 120;
const COOLDOWN_STORAGE_KEY = 'verify-email-resend-next-at';
const remainingSeconds = ref(0);
let cooldownIntervalId = null;

const VERIFY_POLL_INTERVAL_MS = 5000;
let verifyPollIntervalId = null;
let verifyRedirected = false;

const form = useForm({});

const resendButtonText = computed(() => {
    if (remainingSeconds.value <= 0) {
        return 'Siųsti patvirtinimo nuorodą dar kartą';
    }

    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = String(remainingSeconds.value % 60).padStart(2, '0');

    return `Galėsite siųsti po ${minutes}:${seconds}`;
});

const isResendDisabled = computed(() => form.processing || remainingSeconds.value > 0);

function getRemainingCooldown() {
    const nextAtRaw = window.localStorage.getItem(COOLDOWN_STORAGE_KEY);
    const nextAt = Number(nextAtRaw);
    if (!nextAt || Number.isNaN(nextAt)) {
        return 0;
    }

    const diffMs = nextAt - Date.now();

    return diffMs > 0 ? Math.ceil(diffMs / 1000) : 0;
}

function startCooldown(seconds = COOLDOWN_SECONDS) {
    const nextAt = Date.now() + (seconds * 1000);
    window.localStorage.setItem(COOLDOWN_STORAGE_KEY, String(nextAt));
    remainingSeconds.value = seconds;

    if (cooldownIntervalId !== null) {
        window.clearInterval(cooldownIntervalId);
    }

    cooldownIntervalId = window.setInterval(() => {
        remainingSeconds.value = getRemainingCooldown();
        if (remainingSeconds.value <= 0 && cooldownIntervalId !== null) {
            window.clearInterval(cooldownIntervalId);
            cooldownIntervalId = null;
            window.localStorage.removeItem(COOLDOWN_STORAGE_KEY);
        }
    }, 1000);
}

function openSuccessModal(text) {
    successModalText.value = text || 'Patvirtinimo nuoroda išsiųsta. Patikrinkite el. paštą.';
    showSuccessModal.value = true;
    resendError.value = '';
}

function resend() {
    if (isResendDisabled.value) {
        return;
    }

    resendError.value = '';
    form.post(route('verification.send'), {
        preserveScroll: true,
        onSuccess: async () => {
            startCooldown();
            await nextTick();
            const msg = page.props.flash?.status;
            openSuccessModal(typeof msg === 'string' && msg !== '' ? msg : '');
        },
        onError: () => {
            const cooldown = getRemainingCooldown();
            if (cooldown > 0) {
                remainingSeconds.value = cooldown;
                resendError.value = 'Laiškas jau išsiųstas. Pabandykite dar kartą po 2 min.';
            } else {
                resendError.value =
                    'Nepavyko išsiųsti patvirtinimo nuorodos. Pabandykite vėliau.';
            }
        },
    });
}

function stopVerifyPoll() {
    if (verifyPollIntervalId !== null) {
        window.clearInterval(verifyPollIntervalId);
        verifyPollIntervalId = null;
    }
}

function checkVerified() {
    if (verifyRedirected) return;
    if (page.props.auth?.user?.email_verified_at) {
        verifyRedirected = true;
        stopVerifyPoll();
        router.visit(route('dashboard'));
    }
}

function startVerifyPoll() {
    stopVerifyPoll();
    verifyPollIntervalId = window.setInterval(() => {
        router.reload({
            only: ['auth'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: checkVerified,
        });
    }, VERIFY_POLL_INTERVAL_MS);
}

onMounted(() => {
    const cooldown = getRemainingCooldown();
    if (cooldown > 0) {
        startCooldown(cooldown);
    }

    const initial = page.props.flash?.status;
    if (typeof initial === 'string' && initial !== '') {
        openSuccessModal(initial);
    }

    checkVerified();
    startVerifyPoll();
});

onBeforeUnmount(() => {
    if (cooldownIntervalId !== null) {
        window.clearInterval(cooldownIntervalId);
        cooldownIntervalId = null;
    }
    stopVerifyPoll();
});
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
                <p v-if="resendError" class="verify-email-error" role="alert">
                    {{ resendError }}
                </p>
                <div class="verify-email-actions">
                    <AppButton
                        :text="resendButtonText"
                        :as-button="true"
                        :loading="form.processing"
                        :disabled="isResendDisabled"
                        @click="resend"
                    />
                </div>
            </div>
        </main>

        <LandingFooter />

        <SuccessModal
            :show="showSuccessModal"
            :message="successModalText"
            @close="showSuccessModal = false"
        />
    </div>
</template>

<style scoped>
.verify-email-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
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

.verify-email-error {
    margin: 0 0 1rem 0;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 14px;
    line-height: 1.45;
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
