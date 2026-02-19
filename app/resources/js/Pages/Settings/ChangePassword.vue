<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useSuccessModal } from '@/composables/useSuccessModal.js'

const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updatePassword = () => {
    passwordForm.put(route('settings.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <Head title="Keisti slaptažodį" />

    <AuthenticatedLayout>
        <div class="change-password-page">
            <SuccessModal
                :show="showSuccessModal"
                :message="successMessage"
                @close="closeSuccessModal"
            />
            <div v-if="passwordForm.errors.password" class="error-message">{{ passwordForm.errors.password }}</div>
            <form @submit.prevent="updatePassword" class="change-password-form">
                <AppInput
                    v-model="passwordForm.current_password"
                    type="password"
                    placeholder="Esamas slaptažodis"
                />
                <AppInput
                    v-model="passwordForm.password"
                    type="password"
                    placeholder="Naujas slaptažodis"
                />
                <AppInput
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    placeholder="Pakartoti slaptažodį"
                />
                <AppButton
                    text="Patvirtinti"
                    variant="primary"
                    :disabled="passwordForm.processing"
                />
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.change-password-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 120px);
    padding: 24px;
    box-sizing: border-box;
}

.change-password-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    max-width: 400px;
    box-sizing: border-box;
}
.error-message {
    color: #b91c1c;
    margin-bottom: 12px;
}
</style>
