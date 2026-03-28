<script setup>
import { router } from '@inertiajs/vue3'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { ICON_SUCCESS } from '@/constants/ui/icons'

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
            <img :src="ICON_SUCCESS" alt="" class="reporter-success-icon" />
            <p class="reporter-success-text">
                <span class="reporter-success-line">Pranešimas sėkmingai</span>
                <span class="reporter-success-line">užregistruota!</span>
            </p>
        </div>

        <template #footer>
            <AppButton
                text="Peržiūrėti"
                variant="secondary"
                as-button
                class="reporter-success-btn-perziureti"
                @click="goToPerziureti"
            />
            <AppButton
                text="Grįžti į pagrindinį"
                variant="primary"
                as-button
                class="reporter-success-btn-main"
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
    padding: 16px 0 24px;
}

.reporter-success-icon {
    display: block;
    margin: 0 auto 16px;
    width: 48px;
    height: 48px;
    object-fit: contain;
}

.reporter-success-text {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

.reporter-success-line {
    display: block;
}

:deep(.modal-footer) {
    align-items: stretch;
    gap: 12px;
}

:deep(.modal-footer) .reporter-success-btn-perziureti,
:deep(.modal-footer) .reporter-success-btn-main {
    width: 100%;
    border-radius: 20px;
}

:deep(.modal-footer) .reporter-success-btn-perziureti {
    color: var(--color-primary);
    border-color: var(--color-primary);
}

:deep(.modal-footer) .reporter-success-btn-perziureti:hover:not(.app-button--disabled) {
    color: var(--color-primary-hover);
    border-color: var(--color-primary-hover);
}
</style>
