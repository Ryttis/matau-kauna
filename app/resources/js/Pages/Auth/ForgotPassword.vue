<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import SuccessModal from '@/Components/App/SuccessModal.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AuthInput from '@/Components/Auth/AuthInput.vue';
import AppButton from '@/Components/App/AppButton.vue';

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
});

const showSuccessModal = ref(!!props.status);
watch(() => props.status, (v) => { if (v) showSuccessModal.value = true }, { immediate: true });

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Slaptažodžio priminimas" />

        <SuccessModal
            :show="showSuccessModal"
            :message="props.status || ''"
            @close="showSuccessModal = false"
        />

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
