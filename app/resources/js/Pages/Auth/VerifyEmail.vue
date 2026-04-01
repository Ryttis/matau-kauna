<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import SuccessModal from '@/Components/App/SuccessModal.vue';
import GuestHeader from '@/Components/Landing/GuestHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';

const page = usePage();

const showSuccessModal = ref(false);
const successModalText = ref('');
const resendError = ref('');

const form = useForm({});

function openSuccessModal(text) {
    successModalText.value = text || 'Patvirtinimo nuoroda išsiųsta. Patikrinkite el. paštą.';
    showSuccessModal.value = true;
    resendError.value = '';
}

function resend() {
    resendError.value = '';
    form.post(route('verification.send'), {
        preserveScroll: true,
        onSuccess: async () => {
            await nextTick();
            const msg = page.props.flash?.status;
            openSuccessModal(typeof msg === 'string' && msg !== '' ? msg : '');
        },
        onError: () => {
            resendError.value =
                'Nepavyko išsiųsti patvirtinimo nuorodos. Pabandykite vėliau.';
        },
    });
}

onMounted(() => {
    const initial = page.props.flash?.status;
    if (typeof initial === 'string' && initial !== '') {
        openSuccessModal(initial);
    }
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
                        text="Siųsti patvirtinimo nuorodą dar kartą"
                        :as-button="true"
                        :loading="form.processing"
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
