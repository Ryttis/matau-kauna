<script setup>
import AuthInput from '@/Components/Auth/AuthInput.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    token: String,
    email: String,
    institution_name: String,
})

const form = useForm({
    name: '',
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('register.invite.store', props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Sukurti paskyrą" />

        <form @submit.prevent="submit" class="register-form">
            <h1 class="register-title">Sukurti paskyrą</h1>
            <p v-if="institution_name" class="register-hint">
                Pakvietimas įstaigai: {{ institution_name }}
            </p>

            <AuthInput
                id="name"
                type="text"
                placeholder="Vardas"
                v-model="form.name"
                :error="form.errors.name"
            />

            <AuthInput
                id="email"
                type="email"
                placeholder="El. paštas"
                v-model="form.email"
                :error="form.errors.email"
                :readonly="true"
            />

            <AuthInput
                id="password"
                type="password"
                placeholder="Slaptažodis"
                v-model="form.password"
                :error="form.errors.password"
            />

            <AuthInput
                id="password_confirmation"
                type="password"
                placeholder="Pakartokite slaptažodį"
                v-model="form.password_confirmation"
                :error="form.errors.password_confirmation"
            />

            <button type="submit" class="submit-btn" :disabled="form.processing">
                Sukurti paskyrą
            </button>
        </form>
    </GuestLayout>
</template>

<style scoped>
.register-form {
    width: 400px;
    margin: 0 auto;
    font-size: 18px;
}

.register-title {
    font-size: 22px;
    margin-bottom: 8px;
}

.register-hint {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 16px;
}

.submit-btn {
    width: 100%;
    padding: 12px;
    margin-top: 12px;
    background: var(--color-primary, #1946A0);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
}

.submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>
