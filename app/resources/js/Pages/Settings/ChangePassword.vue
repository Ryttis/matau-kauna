<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useSuccessModal } from '@/composables/useSuccessModal'

const page = usePage()
const passwordManagerUsername = computed(() => page.props.auth?.user?.email ?? '')

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
            <form @submit.prevent="updatePassword" class="change-password-form change-password-form--with-username">
                <label class="password-form-sr-only" for="change-password-username"
                    >Paskyros el. paštas (slaptažodžių tvarkyklei)</label
                >
                <input
                    id="change-password-username"
                    type="text"
                    name="username"
                    autocomplete="username"
                    :value="passwordManagerUsername"
                    readonly
                    tabindex="-1"
                    class="password-form-username-field"
                />
                <AppInput
                    v-model="passwordForm.current_password"
                    type="password"
                    placeholder="Esamas slaptažodis"
                    autocomplete="current-password"
                />
                <AppInput
                    v-model="passwordForm.password"
                    type="password"
                    placeholder="Naujas slaptažodis"
                    autocomplete="new-password"
                />
                <AppInput
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    placeholder="Pakartoti slaptažodį"
                    autocomplete="new-password"
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

.change-password-form--with-username {
    position: relative;
}

.password-form-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.password-form-username-field {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
    opacity: 0;
    pointer-events: none;
}
.error-message {
    color: var(--color-error);
    margin-bottom: 12px;
}
</style>
