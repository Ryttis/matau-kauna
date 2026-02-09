<script setup>
import { computed } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

const props = defineProps({
    show: Boolean,
    step: {
        type: Number,
        default: 0,
    },
    steps: {
        type: Array,
        required: true,
    },
})

const emit = defineEmits(['close', 'next', 'back'])

const current = computed(() => props.steps[props.step] ?? props.steps[0])

function onPrimary() {
    emit('next')
}

function onSkip() {
    emit('close')
}

function onBack() {
    if (props.step > 0) {
        emit('back')
    } else {
        emit('close')
    }
}
</script>

<template>
    <BaseModal
        :show="show"
        :show-close-button="false"
        :close-on-overlay-click="false"
        :max-width="'420px'"
        :footer-layout="'column'"
        @close="onSkip"
    >
        <template #header>
            <button
                type="button"
                class="wizard-header-btn wizard-back"
                aria-label="Atgal"
                @click="onBack"
            >
                <img src="/icons/left-back-arrow.png" alt="" class="wizard-header-icon" />
            </button>
            <div class="wizard-header-spacer" />
            <button
                type="button"
                class="wizard-header-btn wizard-close"
                aria-label="Uždaryti"
                @click="onSkip"
            >
                <img src="/icons/close-icon.png" alt="" class="wizard-header-icon" />
            </button>
        </template>

        <div class="wizard-body">
            <h2 class="wizard-title">{{ current.title }}</h2>
            <p class="wizard-description">{{ current.description }}</p>
        </div>

        <template #footer>
            <AppButton
                :text="current.primaryLabel"
                variant="primary"
                as-button
                @click="onPrimary"
            />
            <AppButton
                text="Praleisti"
                variant="outline-light"
                as-button
                @click="onSkip"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.wizard-header-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 50%;
}

.wizard-header-btn:hover {
    background: #f0f0f0;
}

.wizard-header-icon {
    display: block;
    width: 16px;
    height: 16px;
}

.wizard-back .wizard-header-icon {
    filter: brightness(0);
}

.wizard-header-spacer {
    flex: 1;
}

.wizard-body {
    text-align: center;
    padding: 8px 0 0;
}

.wizard-title {
    margin: 0 0 12px;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

.wizard-description {
    margin: 0 0 24px;
    font-size: 16px;
    line-height: 1.5;
    color: #666;
    font-family: 'Open Sans', sans-serif;
}

:deep(.modal-footer) {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
}

:deep(.modal-footer) .app-button {
    width: 100%;
}
</style>
