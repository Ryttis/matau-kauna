<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue';
import LoginActions from '@/Components/Auth/Login/LoginActions.vue';
import LoginNavigation from '@/Components/Auth/Login/LoginNavigation.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="login-form">
            <LoginNavigation />

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
        </form>
    </GuestLayout>
</template>

<style scoped>
.login-form {
    width: 400px;
    margin: 0 auto;
    font-size: 18px;
}
</style>
