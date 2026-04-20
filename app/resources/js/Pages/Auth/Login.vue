<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';
import SuccessModal from '@/Components/App/SuccessModal.vue';
import LoginNavigation from '@/Components/Auth/Login/LoginNavigation.vue';
import GuestHeader from '@/Components/Landing/GuestHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
        default: '',
    },
});

const showSuccessModal = ref(!!props.status);
watch(() => props.status, (v) => { if (v) showSuccessModal.value = true }, { immediate: true });
const activeRole = ref('pareiskejas');

const form = useForm({
    email: '',
    password: '',
    remember: false,
    login_type: 'pareiskejas',
});

watch(activeRole, (tab) => {
    form.login_type = tab;
});

const emailError = computed(() => form.errors.email || page.props.errors?.email);

const viispLoading = ref(false);

const submit = () => {
    form.login_type = activeRole.value;
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        showProgress: false,
    });
};

const goViisp = () => {
    if (viispLoading.value) {
        return;
    }
    viispLoading.value = true;
    window.location.assign(route('viisp.redirect'));
};
</script>

<template>
    <div class="login-page">
        <Head title="Prisijungti" />

        <GuestHeader />

        <main class="login-page-main">
            <SuccessModal
                :show="showSuccessModal"
                :message="props.status || ''"
                @close="showSuccessModal = false"
            />
            <div class="login-form-card">
                <form @submit.prevent="submit" class="login-form">
                    <LoginNavigation
                        :active-tab="activeRole"
                        @select="activeRole = $event"
                    />

                    <AuthInput
                        :id="activeRole === 'darbuotojas' ? 'email' : 'email-pareiskejas'"
                        type="email"
                        placeholder="Vardas arba el. paštas"
                        v-model="form.email"
                        :error="emailError"
                    />

                    <AuthInput
                        :id="activeRole === 'darbuotojas' ? 'password' : 'password-pareiskejas'"
                        type="password"
                        placeholder="Slaptažodis"
                        v-model="form.password"
                        :error="form.errors.password"
                    />

                    <div class="login-form-actions">
                        <AppButton
                            text="Prisijungti"
                            :as-button="false"
                            :loading="form.processing"
                        />
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="login-form-forgot"
                        >
                            *Priminti slaptažodį
                        </Link>
                        <button
                            v-if="activeRole === 'pareiskejas'"
                            type="button"
                            class="login-form-el-vartai"
                            :disabled="viispLoading"
                            :aria-busy="viispLoading"
                            @click="goViisp"
                        >
                            <span
                                v-if="viispLoading"
                                class="login-form-el-vartai__spinner"
                                aria-hidden="true"
                            />
                            <span v-else>Jungtis per el. valdžios vartus</span>
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f6f8;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.login-page-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.login-status {
    margin-bottom: 1rem;
    color: var(--color-primary, #1946A0);
    font-size: 0.95rem;
}

.login-form-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    padding: 2rem 0;
}

.login-form {
    margin: 0 auto;
    font-size: 18px;
}

.login-form-actions {
    margin: 0 40px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.login-form-actions :deep(.app-button) {
    width: 100%;
    height: 48px;
    min-height: 48px;
    margin-bottom: 12px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1rem;
}

.login-form-forgot {
    display: block;
    text-align: left;
    text-decoration: none;
    color: #666;
    font-size: 14px;
    margin-bottom: 16px;
}

.login-form-forgot:hover {
    color: var(--color-primary, #1946A0);
}

.login-form-el-vartai {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 48px;
    min-height: 48px;
    padding: 0;
    border-radius: 8px;
    font-size: 1rem;
    text-align: center;
    text-decoration: none;
    color: #666;
    background: #fff;
    border: 1px solid #ccc;
    box-sizing: border-box;
    cursor: pointer;
    font-family: inherit;
}

.login-form-el-vartai:hover:not(:disabled) {
    background: #f5f5f5;
    color: #333;
}

.login-form-el-vartai:disabled {
    cursor: wait;
    opacity: 0.85;
}

.login-form-el-vartai__spinner {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid rgba(102, 102, 102, 0.25);
    border-top-color: #666;
    animation: login-viisp-spin 0.65s linear infinite;
    box-sizing: border-box;
}

@keyframes login-viisp-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
