<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';
import GuestHeader from '@/Components/Landing/GuestHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const PASSWORD_RULES = {
    minLength: { check: (v) => (v || '').length >= 8, label: 'Bent 8 simboliai' },
    uppercase: { check: (v) => /[A-Z]/.test(v || ''), label: 'Bent viena didžioji raidė' },
    lowercase: { check: (v) => /[a-z]/.test(v || ''), label: 'Bent viena mažoji raidė' },
    symbol: { check: (v) => /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(v || ''), label: 'Bent vienas simbolis' },
};

const passwordErrors = computed(() => {
    const p = form.password || '';
    return Object.entries(PASSWORD_RULES)
        .filter(([, rule]) => !rule.check(p))
        .map(([, rule]) => rule.label);
});

const passwordValid = computed(() => passwordErrors.value.length === 0);

const confirmationError = computed(() => {
    if (!form.password_confirmation) return '';
    return form.password !== form.password_confirmation ? 'Slaptažodžiai turi sutapti' : '';
});

const canSubmit = computed(() => {
    return form.name.trim() !== '' &&
        form.email.trim() !== '' &&
        form.password !== '' &&
        passwordValid.value &&
        form.password_confirmation !== '' &&
        form.password === form.password_confirmation;
});

const submit = () => {
    if (!canSubmit.value) return;
    form.post(route('register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="register-page">
        <Head title="Registruotis" />

        <GuestHeader />

        <main class="register-page-main">
            <form @submit.prevent="submit" class="register-form">
                <div class="register-field">
                    <AuthInput
                        id="name"
                        type="text"
                        placeholder="Vardas"
                        v-model="form.name"
                        :error="form.errors.name"
                    />
                </div>

                <div class="register-field">
                    <AuthInput
                        id="email"
                        type="email"
                        placeholder="El. paštas"
                        v-model="form.email"
                        :error="form.errors.email"
                    />
                </div>

                <div class="register-field">
                    <AuthInput
                        id="password"
                        type="password"
                        placeholder="Slaptažodis"
                        v-model="form.password"
                        :error="form.errors.password"
                    />
                    <div v-if="form.password" class="password-errors">
                        <template v-if="passwordErrors.length">
                            <p class="password-errors-title">Klaida!</p>
                            <ul class="password-errors-list">
                                <li v-for="msg in passwordErrors" :key="msg">{{ msg }}</li>
                            </ul>
                        </template>
                    </div>
                </div>

                <div class="register-field">
                    <AuthInput
                        id="password_confirmation"
                        type="password"
                        placeholder="Slaptažodžio patvirtinimas"
                        v-model="form.password_confirmation"
                        :error="form.errors.password_confirmation || confirmationError"
                    />
                </div>

                <div class="register-field register-field--actions">
                    <AppButton
                        text="Registruotis"
                        :disabled="!canSubmit || form.processing"
                    />
                </div>
            </form>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.register-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: 'Open Sans', sans-serif;
}

.register-page-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.register-form {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    font-size: 18px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.register-field {
    width: 100%;
}

.register-field :deep(.form-input-wrp) {
    margin-left: 0;
    margin-right: 0;
}

.register-field--actions :deep(.app-button) {
    width: 100%;
    height: var(--button-height, 45px);
    min-height: var(--button-height, 45px);
    margin-bottom: 0;
}

.password-errors {
    margin-top: -4px;
    margin-bottom: 10px;
}

.password-errors-title {
    color: #c00;
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 4px 0;
}

.password-errors-list {
    color: #c00;
    font-size: 14px;
    margin: 0;
    padding-left: 1.25rem;
}

.password-errors-list li {
    margin-bottom: 2px;
}
</style>
