<script setup>
import { router } from '@inertiajs/vue3'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

const props = defineProps({
    show: Boolean,
    problemId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close'])

function goToMain() {
    emit('close')
    router.visit(route('dashboard'))
}

function goToPerziureti() {
    emit('close')
    if (props.problemId) {
        router.visit(route('reports.show', props.problemId))
    } else {
        router.visit(route('dashboard'))
    }
}
</script>

<template>
    <BaseModal
        :show="show"
        :z-index="1002"
        :show-close-button="true"
        :max-width="'420px'"
        :footer-layout="'column'"
        @close="emit('close')"
    >
        <template #header>
            <div class="reporter-success-header-spacer" />
        </template>
        <div class="reporter-success-body">
            <img src="/icons/baseline-check-icon.png" alt="" class="reporter-success-icon" />
            <p class="reporter-success-text">Pranešimas sėkmingai užregistruota!</p>
        </div>
        <template #footer>
            <AppButton
                text="Peržiūrėti"
                variant="outline-light"
                as-button
                @click="goToPerziureti"
            />
            <AppButton
                text="Grįžti į pagrindinį"
                variant="primary"
                as-button
                @click="goToMain"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.reporter-success-header-spacer {
    flex: 1;
}

.reporter-success-body {
    text-align: center;
    padding: 8px 0 16px;
}

.reporter-success-icon {
    display: block;
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    object-fit: contain;
}

.reporter-success-text {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

:deep(.modal-footer) {
    align-items: stretch;
    gap: 10px;
}

:deep(.modal-footer) .app-button {
    width: 100%;
}
</style>
