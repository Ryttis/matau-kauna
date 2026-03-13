<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

defineProps({
    open: Boolean,
    title: {
        type: String,
        default: ''
    },
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
    },
    footerLayout: {
        type: String,
        default: 'row',
        validator: (v) => ['row', 'column'].includes(v)
    },
    confirmVariant: {
        type: String,
        default: 'danger',
        validator: (v) => ['primary', 'danger'].includes(v)
    },
    width: {
        type: String,
        default: '520px'
    },
    maxHeight: {
        type: String,
        default: null
    }
})

const emit = defineEmits(['close', 'confirm'])
</script>

<template>
    <BaseModal
        class="confirm-modal"
        :open="open"
        :title="title"
        :width="width"
        :max-height="maxHeight"
        :footer-layout="footerLayout"
        @close="emit('close')"
    >
        <p class="confirm-modal-message">{{ message }}</p>

        <template #footer>
            <template v-if="footerLayout === 'column'">
                <AppButton
                    :text="loading ? '...' : confirmText"
                    variant="primary"
                    as-button
                    :disabled="loading"
                    :class="{ 'confirm-modal-danger-btn': confirmVariant === 'danger' }"
                    @click="emit('confirm')"
                />
                <AppButton
                    :text="cancelText"
                    variant="secondary"
                    as-button
                    @click="emit('close')"
                />
            </template>
            <template v-else>
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
                    :class="{ 'confirm-modal-danger-btn': confirmVariant === 'danger' }"
                    @click="emit('confirm')"
                />
            </template>
        </template>
    </BaseModal>
</template>

<style scoped>
.confirm-modal-message {
    text-align: center;
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
    margin: 0;
    line-height: 1.5;
}

.confirm-modal :deep(.modal-body) {
    padding-top: 8px;
    padding-bottom: 16px;
}

.confirm-modal-danger-btn {
    background-color: #b42318 !important;
    border-color: #b42318 !important;
}

.confirm-modal-danger-btn:hover:not(:disabled) {
    background-color: #a01e14 !important;
}

.confirm-modal :deep(.modal-footer) {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 20px;
}

.confirm-modal :deep(.modal-footer .app-button) {
    width: auto;
    min-width: 100px;
    flex: 0 0 auto;
    margin-bottom: 0;
}
</style>
