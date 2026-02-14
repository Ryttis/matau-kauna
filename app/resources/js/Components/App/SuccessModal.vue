<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'

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
            <div class="success-modal-spacer" />
        </template>
        <div class="success-modal-body">
            <img src="/icons/baseline-check-icon.png" alt="" class="success-modal-icon" />
            <p class="success-modal-text">{{ message }}</p>
        </div>
    </BaseModal>
</template>

<style scoped>
.success-modal-spacer {
    flex: 1;
}

.success-modal-body {
    text-align: center;
    padding: 8px 0 24px;
}

.success-modal-icon {
    display: block;
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    object-fit: contain;
}

.success-modal-text {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
    line-height: 1.4;
}
</style>
