<script setup>
import AppButton from '@/Components/App/AppButton.vue'

defineProps({
    cancelText: { type: String, default: 'Atšaukti' },
    primaryText: { type: String, default: 'Išsaugoti' },
    primaryDisabled: { type: Boolean, default: false },
})

const emit = defineEmits(['cancel', 'primary'])

function handleCancel() {
    emit('cancel')
}

function handlePrimary() {
    emit('primary')
}
</script>

<template>
    <div class="modal-footer-actions">
        <AppButton
            :text="cancelText"
            variant="secondary"
            as-button
            class="modal-footer-actions__btn"
            @click="handleCancel"
        />
        <AppButton
            :text="primaryText"
            variant="primary"
            as-button
            class="modal-footer-actions__btn"
            :disabled="primaryDisabled"
            @click="handlePrimary"
        />
    </div>
</template>

<style scoped>
.modal-footer-actions {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    gap: 12px;
    justify-content: flex-end;
    width: 100%;
}

.modal-footer-actions__btn {
    width: auto !important;
    min-width: 100px;
    flex: 0 0 auto;
}
</style>
