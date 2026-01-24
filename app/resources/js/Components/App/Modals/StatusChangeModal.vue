<script setup>
import { ref, watch } from 'vue'
import { PROBLEM_STATUS_OPTIONS } from '@/constants/problemStatus'

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

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.problem) {
            selectedStatus.value = props.problem.status || ''
        } else if (props.selectedProblemIds && props.selectedProblemIds.length > 0) {
            selectedStatus.value = ''
        } else {
            selectedStatus.value = ''
        }
    }
}, { immediate: true })

watch(() => props.problem, (newProblem) => {
    if (newProblem && props.show) {
        selectedStatus.value = newProblem.status || ''
    }
}, { immediate: true })

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
</script>

<template>
    <div v-if="show" class="status-modal-overlay" @click="handleClose">
        <div class="status-modal-content" @click.stop>
            <div class="status-modal-header">
                <button @click="handleClose" class="status-modal-close-header">
                    <svg width="24" height="24" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="8" cy="8" r="7.5" stroke="#333333" stroke-width="1.5" fill="none"/>
                        <path d="M5 5L11 11M11 5L5 11" stroke="#333333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <div class="status-modal-body">
                <div
                    v-for="option in statusOptions"
                    :key="option.value"
                    class="status-modal-option"
                    :class="{ 'status-modal-option-active': selectedStatus === option.value }"
                    @click="handleStatusSelect(option.value)"
                >
                    {{ option.label }}
                </div>
            </div>

            <div class="status-modal-footer">
                <button class="status-modal-save-btn" @click="handleSave">
                    Saugoti
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.status-modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background: rgba(0, 0, 0, 0.5) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 1000 !important;
}

.status-modal-content {
    background: #ffffff !important;
    border-radius: 5px !important;
    padding: 0 !important;
    min-width: 350px !important;
    max-width: 400px !important;
    width: 100% !important;
    position: relative !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    overflow: hidden !important;
}

.status-modal-header {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    padding: 8px 16px !important;
    position: relative !important;
}

.status-modal-close-header {
    background: transparent !important;
    border: none !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    width: 48px !important;
    height: 48px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    transition: opacity 0.2s !important;
}

.status-modal-close-header:hover {
    opacity: 0.7 !important;
}

.status-modal-close-header svg {
    display: block !important;
    width: 24px !important;
    height: 24px !important;
}

.status-modal-body {
    margin: 0 !important;
    padding: 16px !important;
}

.status-modal-option {
    padding: 0 !important;
    margin-bottom: 6px !important;
    cursor: pointer !important;
    font-size: 18px !important;
    color: #1E1C1C !important;
    transition: color 0.2s !important;
    user-select: none !important;
    text-align: left !important;
    border: none !important;
    background: none !important;
    width: 100% !important;
    font-family: 'Open Sans', sans-serif !important;
    line-height: 150% !important;
    letter-spacing: -1.1% !important;
    font-weight: 400 !important;
    display: block !important;
}

.status-modal-option:last-child {
    margin-bottom: 0 !important;
}

.status-modal-option:not(:last-child) {
    margin-bottom: 6px !important;
}

.status-modal-option:hover {
    color: #1946A0 !important;
}

.status-modal-option-active {
    color: #1946A0 !important;
    font-weight: 400 !important;
}

.status-modal-footer {
    display: flex !important;
    justify-content: center !important;
    margin-top: 16px !important;
    padding: 0 16px 16px 16px !important;
}

.status-modal-save-btn {
    width: auto !important;
    min-width: 202px !important;
    height: 45px !important;
    background-color: #1946A0 !important;
    color: #ffffff !important;
    border: 1px solid #1946A0 !important;
    border-radius: 5px !important;
    padding: 0 24px !important;
    font-size: 18px !important;
    font-weight: 400 !important;
    font-family: 'Open Sans', sans-serif !important;
    line-height: 150% !important;
    letter-spacing: -1.1% !important;
    cursor: pointer !important;
    transition: background-color 0.2s !important;
    box-sizing: border-box !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
}

.status-modal-save-btn:hover {
    background-color: #1a3d95 !important;
}

.status-modal-save-btn:active {
    background-color: #163882 !important;
}
</style>
