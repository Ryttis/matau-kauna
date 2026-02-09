<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

defineProps({
    show: Boolean,
})

const emit = defineEmits(['close', 'confirmCancel'])

function onToliau() {
    emit('close')
}

function onPraleisti() {
    emit('confirmCancel')
}
</script>

<template>
    <BaseModal
        :show="show"
        :z-index="1001"
        title="Atšaukti pranešimo registraciją?"
        :max-width="'440px'"
        :footer-layout="'column'"
        @close="emit('close')"
    >
        <p class="reporter-cancel-text">
            Išėjus iš registracijos lango, bus prarasta užpildyta informacija.
        </p>
        <template #footer>
            <AppButton
                text="Toliau"
                variant="primary"
                as-button
                @click="onToliau"
            />
            <AppButton
                text="Praleisti"
                variant="outline-light"
                as-button
                @click="onPraleisti"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.reporter-cancel-text {
    margin: 0;
    font-size: 16px;
    line-height: 1.5;
    color: #333;
}

:deep(.modal-footer) {
    align-items: stretch;
    gap: 10px;
}

:deep(.modal-footer) .app-button {
    width: 100%;
}
</style>
