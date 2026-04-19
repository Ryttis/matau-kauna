<script setup>
import { ref, watch } from 'vue'
import {
    PROBLEM_STATUS,
    STATUS_CHANGE_MODAL_OPTIONS,
    getStatusModalVars,
} from '@/constants/domain/problemStatus'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { MODAL_BUTTON_HEIGHT, MODAL_BUTTON_MIN_WIDTH } from '@/constants/ui/theme'

const props = defineProps({
    show: Boolean,
    problem: Object,
    selectedProblemIds: {
        type: Array,
        default: () => []
    },
    saving: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'save'])

const statusOptions = STATUS_CHANGE_MODAL_OPTIONS

const selectedStatus = ref('')

watch(
    () => [props.show, props.problem],
    () => {
        if (!props.show) return
        const s = props.problem?.status ?? ''
        selectedStatus.value = s === PROBLEM_STATUS.NEW ? '' : s
    },
    { immediate: true }
)

const handleStatusSelect = (status) => {
    selectedStatus.value = status
}

const handleSave = () => {
    if (props.saving) return
    if (selectedStatus.value) {
        emit('save', selectedStatus.value)
    }
}

const handleClose = () => {
    if (props.saving) return
    emit('close')
}

const btnHeightPx = `${MODAL_BUTTON_HEIGHT}px`
const btnMinWidthPx = `${MODAL_BUTTON_MIN_WIDTH}px`
</script>

<template>
    <BaseModal
        :show="show"
        :max-width="'360px'"
        :min-width="'300px'"
        footer-layout="column"
        @close="handleClose"
    >
        <template #header>
            <div class="status-modal-header-empty"></div>
        </template>

        <div class="status-modal-body" :style="{ '--status-modal-btn-min-width': btnMinWidthPx }">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="status-modal-option-btn"
                :class="{ 'status-modal-option-btn-active': selectedStatus === option.value }"
                :style="{ minHeight: btnHeightPx, minWidth: btnMinWidthPx, ...getStatusModalVars(option.value) }"
                @click="handleStatusSelect(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <template #footer>
            <div class="status-modal-footer-wrapper">
                <AppButton
                    text="Saugoti"
                    variant="primary"
                    as-button
                    class="status-modal-save-btn-app"
                    :loading="saving"
                    :disabled="saving || !selectedStatus"
                    @click="handleSave"
                />
            </div>
        </template>
    </BaseModal>
</template>

<style scoped>
.status-modal-header-empty {
    flex: 1;
}

.status-modal-body {
    padding: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

:deep(.modal-body) {
    padding: 12px 16px;
}

.status-modal-option-btn {
    width: auto;
    min-width: var(--status-modal-btn-min-width);
    padding: 0 16px;
    background: var(--color-white, #fff);
    border: 2px solid var(--status-border);
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    color: var(--color-text, #1E1C1C);
    transition: border-color 0.2s, background-color 0.2s;
    user-select: none;
    text-align: center;
    font-family: 'Open Sans', sans-serif;
    line-height: 1.5;
    font-weight: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.status-modal-option-btn:hover {
    border-color: var(--status-border-hover);
    background-color: var(--status-bg-hover);
}

.status-modal-option-btn.status-modal-option-btn-active {
    border-color: var(--status-border-hover);
    background-color: var(--status-bg-hover);
    border-width: 3px;
}

.status-modal-footer-wrapper {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.status-modal-save-btn-app {
    width: var(--button-width);
    max-width: 100%;
    height: var(--button-height);
    min-height: var(--button-height);
    border-radius: var(--button-border-radius);
    padding: 9px 24px;
    box-sizing: border-box;
}
</style>
