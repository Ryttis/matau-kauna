<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'

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
            <button type="button" class="secondary-btn" @click="emit('close')">
                {{ cancelText }}
            </button>

            <button type="button" class="danger-btn" :disabled="loading" @click="emit('confirm')">
                {{ loading ? '...' : confirmText }}
            </button>
        </template>
    </BaseModal>
</template>

<style scoped>
.secondary-btn {
    border: 1px solid #e6e8ee;
    background: #ffffff;
    color: #1946A0;
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    font-weight: 600;
}

.danger-btn {
    border: none;
    background: #b42318;
    color: #ffffff;
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    font-weight: 600;
}

.danger-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
