<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Ar tikrai norite šalinti paskyrą?',
    },
    confirmLoading: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'confirm'])

const handleClose = () => emit('close')
const handleConfirm = () => emit('confirm')
</script>

<template>
    <div class="delete-account-modal-root">
        <BaseModal
            :open="open"
            title=""
            width="420px"
            :footer-layout="'column'"
            @close="handleClose"
        >
            <div class="delete-account-modal">
                <p class="delete-account-modal__question">{{ title }}</p>
            </div>

            <template #footer>
            <AppButton
                text="Patvirtinti"
                variant="primary"
                as-button
                :disabled="confirmLoading"
                class="delete-account-modal__btn-confirm"
                @click="handleConfirm"
            />
            <AppButton
                text="Atšaukti"
                variant="secondary"
                as-button
                class="delete-account-modal__btn-cancel"
                @click="handleClose"
            />
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.delete-account-modal {
    text-align: center;
}

.delete-account-modal__question {
    font-size: 18px;
    font-weight: 700;
    color: #000;
    margin: 0 0 20px;
    line-height: 1.4;
}

.delete-account-modal-root :deep(.modal-header) {
    border-bottom: none;
}

.delete-account-modal-root :deep(.modal-footer) {
    border-top: none;
    padding-top: 8px;
}

.delete-account-modal__btn-confirm,
.delete-account-modal__btn-cancel {
    width: 100%;
    max-width: 200px;
    height: 40px;
    border-radius: 12px;
    padding: 0 20px;
}

.delete-account-modal__btn-confirm {
    background-color: var(--color-primary-dark, #2E4E93) !important;
    border-color: var(--color-primary-dark, #2E4E93) !important;
    color: var(--color-white, #fff) !important;
}

.delete-account-modal__btn-confirm:hover:not(:disabled) {
    background-color: var(--color-primary-dark-hover, #264080) !important;
}

.delete-account-modal__btn-cancel {
    background-color: var(--color-white, #fff) !important;
    color: var(--color-primary-dark, #2E4E93) !important;
    border: 1px solid var(--color-primary-dark, #2E4E93) !important;
}

.delete-account-modal__btn-cancel:hover {
    background-color: #f5f5f5 !important;
}
</style>
