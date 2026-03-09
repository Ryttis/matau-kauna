<script setup>
import { computed } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import { ICON_LEFT_ARROW, ICON_CLOSE } from '@/constants/icons'

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
        :width="'370px'"
        :max-width="'370px'"
        :footer-layout="'column'"
        class="reporter-wizard-modal"
        @close="onSkip"
    >
        <template #header>
            <button
                type="button"
                class="wizard-header-btn wizard-back"
                aria-label="Atgal"
                @click="onBack"
            >
                <img :src="ICON_LEFT_ARROW" alt="" class="wizard-header-icon" />
            </button>
            <div class="wizard-header-spacer" />
            <button
                type="button"
                class="wizard-header-btn wizard-close"
                aria-label="Uždaryti"
                @click="onSkip"
            >
                <img :src="ICON_CLOSE" alt="" class="wizard-header-icon" />
            </button>
        </template>

        <div class="wizard-body">
            <h2 class="wizard-title">{{ current.title }}</h2>
            <p class="wizard-description">{{ current.description }}</p>
        </div>

        <template #footer>
            <div class="wizard-footer-inner">
                <button
                    type="button"
                    class="wizard-primary-btn"
                    @click="onPrimary"
                >
                    {{ current.primaryLabel }}
                </button>
                <button
                    type="button"
                    class="wizard-skip-btn"
                    @click="onSkip"
                >
                    Praleisti
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<style scoped>
:deep(.reporter-wizard-modal .modal) {
    width: 370px;
    min-height: 360px;
    max-height: 360px;
    border-radius: 5px;
    overflow: hidden;
}

:deep(.reporter-wizard-modal .modal-header) {
    padding: 16px 16px 0;
}

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
    padding: 16px 16px 8px;
    max-width: 344px;
    margin: 0 auto;
}

.wizard-title {
    margin: 0 0 16px;
    font-size: 20px;
    font-weight: 700;
    color: #333;
    font-family: 'Open Sans', sans-serif;
    line-height: 1.3;
}

.wizard-description {
    margin: 0 0 24px;
    font-size: 16px;
    line-height: 1.5;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

:deep(.reporter-wizard-modal .modal-body) {
    padding: 8px 16px 16px;
}

:deep(.reporter-wizard-modal .modal-footer) {
    flex-direction: column;
    align-items: center;
    padding: 0 16px 16px;
}

.wizard-footer-inner {
    width: 202px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 24px;
}

.wizard-primary-btn {
    width: 100%;
    height: 40px;
    padding: 0 24px;
    border: none;
    border-radius: 20px;
    background: var(--color-primary);
    color: var(--color-white, #fff);
    font-size: 16px;
    font-weight: 500;
    font-family: 'Open Sans', sans-serif;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wizard-primary-btn:hover {
    background: var(--color-primary-hover);
}

.wizard-skip-btn {
    display: block;
    width: 100%;
    padding: 8px 16px;
    margin: 0;
    border: none;
    border-radius: 20px;
    background: none;
    font-size: 16px;
    font-weight: 400;
    color: var(--color-primary);
    cursor: pointer;
    font-family: 'Open Sans', sans-serif;
    text-align: center;
}

.wizard-skip-btn:hover {
    opacity: 0.85;
}
</style>
