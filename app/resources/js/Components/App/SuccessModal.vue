<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import { ICON_SUCCESS } from '@/constants/icons.js'

defineProps({
    show: Boolean,
    message: {
        type: String,
        default: ''
    },
})

const emit = defineEmits(['close'])
</script>

<template>
    <BaseModal
        :show="show"
        :z-index="1002"
        :show-close-button="true"
        :max-width="'420px'"
        @close="emit('close')"
    >
        <template #header>
            <div class="success-modal-header-spacer" />
        </template>
        <div class="success-modal-body">
            <img :src="ICON_SUCCESS" alt="" class="success-modal-icon" />
            <p class="success-modal-text">{{ message || 'Operacija atlikta sėkmingai.' }}</p>
        </div>
    </BaseModal>
</template>

<style scoped>
.success-modal-header-spacer {
    flex: 1;
    min-width: 0;
}

.success-modal-body {
    text-align: center;
    padding: 0 0 24px;
    margin-top: -20px;
}

.success-modal-icon {
    display: block;
    margin: 0 auto 12px;
    width: 72px;
    height: 72px;
    object-fit: contain;
}

.success-modal-text {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text-muted, #333);
    font-family: 'Inter', sans-serif;
    line-height: 1.4;
}
</style>
