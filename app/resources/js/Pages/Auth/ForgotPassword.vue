<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import SuccessModal from '@/Components/App/SuccessModal.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
});

const page = usePage();

const statusMessage = computed(() => {
    const p = page.props;
    const f = page.flash;
    const s = p?.status ?? f?.status ?? props.status;
    return typeof s === 'string' ? s : '';
});

const showSuccessModal = ref(!!statusMessage.value);

watch(
    statusMessage,
    (v) => {
        if (v) {
            showSuccessModal.value = true;
        }
    },
    { immediate: true },
);

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'), {
        showProgress: false,
        preserveState: false,
        onSuccess: (visitPage) => {
            const msg =
                visitPage.props?.status ??
                visitPage.flash?.status ??
                '';
            if (typeof msg === 'string' && msg) {
                showSuccessModal.value = true;
            }
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Slaptažodžio priminimas" />

        <SuccessModal
            :show="showSuccessModal"
            :message="statusMessage"
            @close="showSuccessModal = false"
        />

        <div class="forgot-password-shell">
            <div class="forgot-password-card">
                <form @submit.prevent="submit" class="forgot-password-form">
                    <h2>Slaptažodžio priminimas</h2>

                    <AuthInput
                        id="email"
                        type="email"
                        placeholder="Įvesti el. paštą"
                        v-model="form.email"
                        :error="form.errors.email"
                    />

                    <div class="btn-wrp">
                        <AppButton
                            text="Gauti nuorodą"
                            :loading="form.processing"
                        />
                    </div>

                    <Link :href="route('login')" class="forgot-password-back">
                        Grįžti į prisijungimą
                    </Link>
                </form>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
.forgot-password-shell {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    background: #f5f6f8;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    margin: -16px calc(-1 * max(16px, env(safe-area-inset-right))) -24px
        calc(-1 * max(16px, env(safe-area-inset-left)));
    padding: 24px max(16px, env(safe-area-inset-right)) 32px
        max(16px, env(safe-area-inset-left));
    min-height: 0;
}

.forgot-password-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    padding: 2rem 0;
    box-sizing: border-box;
}

h2 {
    text-align: center;
    font-weight: bold;
    font-size: 24px;
    margin: 0 0 32px;
}

.forgot-password-form {
    width: 100%;
    margin: 0 auto;
    font-size: 18px;
    box-sizing: border-box;
}

.btn-wrp {
    margin: 8px 40px 0;
}

.btn-wrp :deep(.app-button) {
    width: 100%;
    height: 48px;
    min-height: 48px;
    margin-bottom: 0;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1rem;
}

.forgot-password-back {
    display: block;
    margin: 16px 40px 0;
    text-align: center;
    text-decoration: none;
    color: #666;
    font-size: 14px;
}

.forgot-password-back:hover {
    color: var(--color-primary, #1946A0);
}
</style>
