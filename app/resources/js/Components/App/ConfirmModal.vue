<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

const props = defineProps({
    open: Boolean,
    title: String,
    message: String,
    confirmText: {
        type: String,
        default: 'Confirm'
    },
    cancelText: {
        type: String,
        default: 'Cancel'
    },
    loading: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'confirm'])
</script>

<template>
    <BaseModal
        :open="open"
        :title="title"
        width="520px"
        @close="emit('close')"
    >
        <p>{{ message }}</p>

        <template #footer>
            <AppButton
                :text="cancelText"
                variant="secondary"
                as-button
                @click="emit('close')"
            />
            <AppButton
                :text="loading ? '...' : confirmText"
                variant="primary"
                as-button
                :disabled="loading"
                @click="emit('confirm')"
                class="confirm-modal-danger-btn"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.confirm-modal-danger-btn {
    background-color: #b42318 !important;
    border-color: #b42318 !important;
}

.confirm-modal-danger-btn:hover:not(:disabled) {
    background-color: #a01e14 !important;
}
</style>
