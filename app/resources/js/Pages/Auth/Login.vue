<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';
import LoginActions from '@/Components/Auth/Login/LoginActions.vue';
import LoginNavigation from '@/Components/Auth/Login/LoginNavigation.vue';
import LandingHeader from '@/Components/Landing/LandingHeader.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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
    login_type: 'darbuotojas',
});

watch(activeRole, (tab) => {
    form.login_type = tab;
});

const submit = () => {
    form.login_type = activeRole.value;
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

                <template v-else>
                    <AuthInput
                        id="email-pareiskejas"
                        type="email"
                        placeholder="Paštas"
                        v-model="form.email"
                        :error="form.errors.email"
                    />

                    <AuthInput
                        id="password-pareiskejas"
                        type="password"
                        placeholder="Slaptažodis"
                        v-model="form.password"
                        :error="form.errors.password"
                    />

                    <div class="login-form-actions login-form-actions--pareiskejas">
                        <AppButton text="Prisijungti" :as-button="false" />
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="login-form-forgot"
                        >
                            Priminti slaptažodį
                        </Link>
                        <button
                            type="button"
                            class="login-form-el-vartai"
                            @click.prevent
                        >
                            Prisijungti el. vartais
                        </button>
                    </div>
                </template>
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

.login-form-forgot {
    display: block;
    text-align: center;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    margin-bottom: 10px;
}

.login-form-forgot:hover {
    color: #1946A0;
}

.login-form-el-vartai {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 45px;
    min-height: 45px;
    margin-bottom: 10px;
    padding: 0;
    border-radius: 5px;
    font-size: 18px;
    text-align: center;
    color: #666;
    background: #fff;
    border: 1px solid #ccc;
    box-sizing: border-box;
    cursor: pointer;
    font-family: inherit;
}

.login-form-el-vartai:hover {
    background: #f5f5f5;
    color: #333;
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
