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
                    <img src="/icons/close-icon.png" alt="Uždaryti" class="status-modal-close-icon" />
                </button>
            </div>

            <div class="status-modal-body">
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    class="status-modal-option-btn"
                    :class="{
                        'status-modal-option-btn-active': selectedStatus === option.value,
                        [`status-modal-option-btn-${option.value}`]: true
                    }"
                    @click="handleStatusSelect(option.value)"
                >
                    {{ option.label }}
                </button>
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

.status-modal-close-icon {
    display: block !important;
    width: 24px !important;
    height: 24px !important;
}

.status-modal-body {
    margin: 0 !important;
    padding: 16px !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
}

.status-modal-option-btn {
    width: 100% !important;
    padding: 12px 16px !important;
    background: #ffffff !important;
    border: 2px solid !important;
    border-radius: 5px !important;
    cursor: pointer !important;
    font-size: 18px !important;
    color: #1E1C1C !important;
    transition: all 0.2s !important;
    user-select: none !important;
    text-align: center !important;
    font-family: 'Open Sans', sans-serif !important;
    line-height: 150% !important;
    letter-spacing: -1.1% !important;
    font-weight: 400 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
}

/* Nauja - oranžinė/šviesiai ruda rėmelis */
.status-modal-option-btn-nauja {
    border-color: #D4A574 !important;
}

.status-modal-option-btn-nauja:hover {
    border-color: #C8955F !important;
    background-color: #FFF9F5 !important;
}

.status-modal-option-btn-nauja.status-modal-option-btn-active {
    border-color: #C8955F !important;
    background-color: #FFF9F5 !important;
    border-width: 3px !important;
}

/* Nagrinėjama - geltona rėmelis */
.status-modal-option-btn-nagrinejama {
    border-color: #F5C842 !important;
}

.status-modal-option-btn-nagrinejama:hover {
    border-color: #E5B832 !important;
    background-color: #FFFDF5 !important;
}

.status-modal-option-btn-nagrinejama.status-modal-option-btn-active {
    border-color: #E5B832 !important;
    background-color: #FFFDF5 !important;
    border-width: 3px !important;
}

/* Išspręsta - žalia rėmelis */
.status-modal-option-btn-isspresta {
    border-color: #4CAF50 !important;
}

.status-modal-option-btn-isspresta:hover {
    border-color: #45A049 !important;
    background-color: #F5FFF5 !important;
}

.status-modal-option-btn-isspresta.status-modal-option-btn-active {
    border-color: #45A049 !important;
    background-color: #F5FFF5 !important;
    border-width: 3px !important;
}

/* Neaktuali - tamsiai pilka/juoda rėmelis */
.status-modal-option-btn-neaktuali {
    border-color: #666666 !important;
}

.status-modal-option-btn-neaktuali:hover {
    border-color: #555555 !important;
    background-color: #F5F5F5 !important;
}

.status-modal-option-btn-neaktuali.status-modal-option-btn-active {
    border-color: #555555 !important;
    background-color: #F5F5F5 !important;
    border-width: 3px !important;
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
