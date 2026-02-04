<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';
import LoginActions from '@/Components/Auth/Login/LoginActions.vue';
import LoginNavigation from '@/Components/Auth/Login/LoginNavigation.vue';
import LandingHeader from '@/Components/Landing/LandingHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const activeRole = ref('darbuotojas');

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        showProgress: false,
    });
};
</script>

<template>
    <div class="login-page">
        <Head title="Prisijungti" />

        <LandingHeader />

        <main class="login-page-main">
            <div v-if="status" class="login-status">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="login-form">
                <LoginNavigation
                    :active-tab="activeRole"
                    @select="activeRole = $event"
                />

                <template v-if="activeRole === 'darbuotojas'">
                    <AuthInput
                        id="email"
                        type="email"
                        placeholder="Paštas"
                        v-model="form.email"
                        :error="form.errors.email"
                    />

                    <AuthInput
                        id="password"
                        type="password"
                        placeholder="Slaptažodis"
                        v-model="form.password"
                        :error="form.errors.password"
                    />

                    <LoginActions :can-reset-password="canResetPassword" />
                </template>

                <div v-else class="login-form-actions login-form-actions--pareiskejas">
                    <AppButton text="Prisijungti" :as-button="true" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="login-form-link"
                    >
                        *Priminti slaptažodį
                    </Link>
                    <Link
                        :href="route('register')"
                        class="login-form-register"
                    >
                        Registruotis
                    </Link>
                </div>
            </form>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: 'Open Sans', sans-serif;
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
}

.login-form {
    width: 400px;
    margin: 0 auto;
    font-size: 18px;
}

.login-form-actions {
    margin: 0 40px;
}

.login-form-actions--pareiskejas {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.login-form-actions--pareiskejas :deep(.app-button) {
    width: 100%;
    height: 45px;
    min-height: 45px;
    margin-bottom: 10px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-form-link {
    display: block;
    text-align: center;
    text-decoration: none;
    color: #333;
    font-size: 12px;
    margin-bottom: 10px;
}

.login-form-register {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 45px;
    min-height: 45px;
    padding: 0;
    border-radius: 5px;
    font-size: 18px;
    text-align: center;
    text-decoration: none;
    color: #666;
    background: #fff;
    border: 1px solid #ccc;
    box-sizing: border-box;
}

.login-form-register:hover {
    background: #f5f5f5;
    color: #333;
}
</style>
