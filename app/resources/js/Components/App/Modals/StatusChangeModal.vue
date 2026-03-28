<script setup>
import { ref, watch } from 'vue'
import { PROBLEM_STATUS_OPTIONS, getStatusModalVars } from '@/constants/domain/problemStatus'
import BaseModal from '@/Components/App/BaseModal.vue'
import { MODAL_BUTTON_HEIGHT, MODAL_BUTTON_MIN_WIDTH } from '@/constants/ui/theme'

const props = defineProps({
    show: Boolean,
    problem: Object,
    selectedProblemIds: {
        type: Array,
        default: () => []
    },
})

const emit = defineEmits(['close', 'save'])

const statusOptions = PROBLEM_STATUS_OPTIONS

const selectedStatus = ref('')

watch(
    () => [props.show, props.problem],
    () => {
        if (!props.show) return
        selectedStatus.value = props.problem?.status ?? ''
    },
    { immediate: true }
)

const handleStatusSelect = (status) => {
    selectedStatus.value = status
}

const handleSave = () => {
    if (selectedStatus.value) {
        emit('save', selectedStatus.value)
    }
}

const handleClose = () => {
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
                <button
                    type="button"
                    class="status-modal-save-btn"
                    :style="{ minHeight: btnHeightPx, minWidth: btnMinWidthPx }"
                    @click="handleSave"
                >
                    Saugoti
                </button>
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

.status-modal-save-btn {
    padding: 0 24px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    font-family: 'Open Sans', sans-serif;
    font-weight: 600;
    background-color: var(--color-primary);
    color: var(--color-white);
    transition: background-color 0.2s;
    box-sizing: border-box;
}

.status-modal-save-btn:hover {
    background-color: var(--color-primary-hover);
}
</style>
