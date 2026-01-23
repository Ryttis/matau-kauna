<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

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
                <AppButton text="Gauti nuorodą" />
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
h2 {
    text-align: center;
    font-weight: bold;
    font-size: 24px;
    margin-bottom: 40px;
}

.forgot-password-form {
    width: 400px;
    margin: 0 auto;
    font-size: 18px;
}

.btn-wrp {
    margin: 0 40px;
}
</style>
