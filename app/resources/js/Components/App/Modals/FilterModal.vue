<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    show: Boolean,
    filters: {
        type: Object,
        default: () => ({
            priority: [],
            reportNumber: '',
            problemType: '',
            dateFrom: '',
            dateTo: '',
            status: '',
        })
    },
    categories: Array,
    allStatusOptions: Array,
})

const emit = defineEmits(['close', 'apply', 'clear'])

const localFilters = ref({
    priority: props.filters?.priority || [],
    reportNumber: props.filters?.reportNumber || '',
    problemType: props.filters?.problemType || '',
    dateFrom: props.filters?.dateFrom || '',
    dateTo: props.filters?.dateTo || '',
    status: props.filters?.status || '',
})

const priorityOptions = [
    { value: 'normal', label: 'Įprastas' },
    { value: 'urgent', label: 'Skubus' },
    { value: 'very_urgent', label: 'Labai skubus' },
]

watch(() => props.show, (newVal) => {
    if (newVal) {
        localFilters.value = {
            priority: props.filters?.priority || [],
            reportNumber: props.filters?.reportNumber || '',
            problemType: props.filters?.problemType || '',
            dateFrom: props.filters?.dateFrom || '',
            dateTo: props.filters?.dateTo || '',
            status: props.filters?.status || '',
        }
    }
})

const handlePriorityToggle = (priority) => {
    const index = localFilters.value.priority.indexOf(priority)
    if (index > -1) {
        localFilters.value.priority.splice(index, 1)
    } else {
        localFilters.value.priority.push(priority)
    }
}

const handleApply = () => {
    emit('apply', { ...localFilters.value })
}

const handleClear = () => {
    localFilters.value = {
        priority: [],
        reportNumber: '',
        problemType: '',
        dateFrom: '',
        dateTo: '',
        status: '',
    }
    emit('clear')
}

const handleClose = () => {
    emit('close')
}
</script>

<template>
    <div v-if="show" class="filter-modal-overlay" @click="handleClose">
        <div class="filter-modal-content" @click.stop>

            <div class="filter-modal-header">
                <h2 class="filter-modal-title">Filtruoti</h2>
                <button @click="handleClose" class="filter-modal-close-header">
                    <svg width="24" height="24" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="8" cy="8" r="7.5" stroke="#333333" stroke-width="1.5" fill="none"/>
                        <path d="M5 5L11 11M11 5L5 11" stroke="#333333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="filter-modal-separator"></div>

            <div class="filter-modal-body">
                <div class="filter-section">
                    <label class="filter-label">Prioritetas</label>
                    <div class="priority-options">
                        <label
                            v-for="option in priorityOptions"
                            :key="option.value"
                            class="priority-option"
                        >
                            <input
                                type="checkbox"
                                :value="option.value"
                                :checked="localFilters.priority.includes(option.value)"
                                @change="handlePriorityToggle(option.value)"
                                class="priority-checkbox"
                            />
                            <span 
                                class="priority-label"
                                :class="{ 
                                    'priority-label-very-urgent': option.value === 'very_urgent'
                                }"
                            >
                                {{ option.label }}
                            </span>
                        </label>
                    </div>
                </div>

                <div class="filter-section">
                    <label class="filter-label">Pranešimo numeris</label>
                    <input
                        v-model="localFilters.reportNumber"
                        type="text"
                        class="filter-input"
                        placeholder="Įrašykite numerį"
                    />
                </div>

                <div class="filter-section">
                    <label class="filter-label">Problemos tipas</label>
                    <div class="filter-select-wrapper">
                        <select v-model="localFilters.problemType" class="filter-select">
                            <option value="">Pasirinkti kategorija</option>
                            <option v-for="category in categories" :key="category" :value="category">
                                {{ category }}
                            </option>
                        </select>
                        <div class="filter-select-icon">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="8" cy="8" r="7.5" stroke="#ffffff" stroke-width="1.5" fill="none"/>
                                <path d="M5 7L8 10L11 7" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="filter-section">
                    <label class="filter-label">Data</label>
                    <div class="date-inputs">
                        <div class="date-input-wrapper">
                            <input
                                v-model="localFilters.dateFrom"
                                type="text"
                                class="filter-input date-input"
                                placeholder="dd/mm/yyyy"
                            />
                            <div class="date-icon">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="4" width="10" height="9" rx="1" stroke="#333333" stroke-width="1.5" fill="none"/>
                                    <line x1="3" y1="7" x2="13" y2="7" stroke="#333333" stroke-width="1.5"/>
                                    <line x1="6" y1="4" x2="6" y2="7" stroke="#333333" stroke-width="1.5"/>
                                    <line x1="10" y1="4" x2="10" y2="7" stroke="#333333" stroke-width="1.5"/>
                                </svg>
                            </div>
                        </div>
                        <div class="date-input-wrapper">
                            <input
                                v-model="localFilters.dateTo"
                                type="text"
                                class="filter-input date-input"
                                placeholder="dd/mm/yyyy"
                            />
                            <div class="date-icon">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="4" width="10" height="9" rx="1" stroke="#333333" stroke-width="1.5" fill="none"/>
                                    <line x1="3" y1="7" x2="13" y2="7" stroke="#333333" stroke-width="1.5"/>
                                    <line x1="6" y1="4" x2="6" y2="7" stroke="#333333" stroke-width="1.5"/>
                                    <line x1="10" y1="4" x2="10" y2="7" stroke="#333333" stroke-width="1.5"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-modal-footer">
                <button class="filter-modal-clear-btn" @click="handleClear">
                    Išvalyti filtrą
                </button>
                <button class="filter-modal-apply-btn" @click="handleApply">
                    Filtruoti
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.filter-modal-overlay {
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

.filter-modal-content {
    background: #ffffff !important;
    border-radius: 5px !important;
    padding: 0 !important;
    min-width: 420px !important;
    max-width: 500px !important;
    width: 100% !important;
    position: relative !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    overflow: hidden !important;
}

.filter-modal-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 20px 30px !important;
    position: relative !important;
}

.filter-modal-close-header {
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

.filter-modal-close-header:hover {
    opacity: 0.7 !important;
}

.filter-modal-close-header svg {
    display: block !important;
    width: 24px !important;
    height: 24px !important;
}

.filter-modal-separator {
    height: 1px !important;
    background: #e0e0e0 !important;
    margin: 0 !important;
}

.filter-modal-title {
    font-size: 24px !important;
    font-weight: 400 !important;
    line-height: 150% !important;
    letter-spacing: -1.1% !important;
    color: #333333 !important;
    margin: 0 !important;
    font-family: 'Open Sans', sans-serif !important;
}

.filter-modal-body {
    margin: 0 !important;
    padding: 30px !important;
}

.filter-section {
    margin-bottom: 20px !important;
}

.filter-section:last-child {
    margin-bottom: 0 !important;
}

.filter-label {
    display: block !important;
    margin-bottom: 12px !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    color: #333333 !important;
    font-family: 'Inter', sans-serif !important;
    line-height: 1.5 !important;
}

.priority-options {
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
}

.priority-option {
    display: flex !important;
    align-items: center !important;
    cursor: pointer !important;
    padding: 0 !important;
    margin-bottom: 12px !important;
}

.priority-option:last-child {
    margin-bottom: 0 !important;
}

.priority-checkbox {
    width: 18px !important;
    height: 18px !important;
    margin-right: 10px !important;
    cursor: pointer !important;
    border: 1px solid #1946A0 !important;
    border-radius: 2px !important;
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-color: #ffffff !important;
    position: relative !important;
    flex-shrink: 0 !important;
}

.priority-checkbox:checked {
    background-color: #1946A0 !important;
    border-color: #1946A0 !important;
}

.priority-checkbox:checked::after {
    content: '✓' !important;
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    color: #ffffff !important;
    font-size: 12px !important;
    font-weight: bold !important;
    line-height: 1 !important;
}

.priority-label {
    font-size: 16px !important;
    color: #333333 !important;
    font-family: 'Inter', sans-serif !important;
    line-height: 1.5 !important;
    font-weight: 400 !important;
    padding: 0 !important;
    border-radius: 0 !important;
    display: inline-block !important;
    background-color: transparent !important;
}

.priority-label.priority-label-very-urgent,
.priority-option .priority-label.priority-label-very-urgent,
span.priority-label.priority-label-very-urgent {
    color: #F41616 !important;
}

.filter-input {
    width: 100% !important;
    height: 40px !important;
    padding: 0 15px !important;
    border: 1px solid #e0e0e0 !important;
    border-radius: 5px !important;
    font-size: 16px !important;
    font-family: 'Inter', sans-serif !important;
    color: #333333 !important;
    background-color: #f5f5f5 !important;
    box-sizing: border-box !important;
}

.filter-input:focus {
    outline: none !important;
    border-color: #1946A0 !important;
    background-color: #ffffff !important;
}

.filter-input::placeholder {
    color: #999999 !important;
    font-style: italic !important;
}

.filter-select-wrapper {
    position: relative !important;
    width: 100% !important;
}

.filter-select {
    width: 100% !important;
    height: 48px !important;
    padding: 0 50px 0 15px !important;
    border: none !important;
    border-radius: 5px !important;
    font-size: 16px !important;
    font-family: 'Inter', sans-serif !important;
    color: #ffffff !important;
    background-color: #1946A0 !important;
    cursor: pointer !important;
    box-sizing: border-box !important;
    appearance: none !important;
    text-align: left !important;
}

.filter-select-icon {
    position: absolute !important;
    right: 15px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    pointer-events: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.filter-select-icon svg {
    display: block !important;
    width: 16px !important;
    height: 16px !important;
}

.filter-select option {
    background-color: #ffffff !important;
    color: #333333 !important;
}

.filter-select:focus {
    outline: none !important;
    border-color: #1946A0 !important;
}

.date-inputs {
    display: flex !important;
    gap: 10px !important;
}

.date-input-wrapper {
    position: relative !important;
    flex: 1 !important;
}

.date-input {
    width: 100% !important;
    padding-right: 45px !important;
}

.date-input::-webkit-calendar-picker-indicator {
    display: none !important;
    -webkit-appearance: none !important;
}

.date-input::-webkit-inner-spin-button,
.date-input::-webkit-outer-spin-button {
    display: none !important;
}

.date-icon {
    position: absolute !important;
    right: 15px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    pointer-events: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.date-icon svg {
    display: block !important;
    width: 16px !important;
    height: 16px !important;
}

.filter-modal-footer {
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
    margin-top: 30px !important;
    padding: 0 30px 30px 30px !important;
    align-items: center !important;
}

.filter-modal-clear-btn {
    width: 100% !important;
    max-width: 340px !important;
    background-color: #ffffff !important;
    color: #1946A0 !important;
    border: 1px solid #1946A0 !important;
    border-radius: 5px !important;
    padding: 14px 24px !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    font-family: 'Inter', sans-serif !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    box-sizing: border-box !important;
    text-align: center !important;
}

.filter-modal-clear-btn:hover {
    background-color: #f5f5f5 !important;
}

.filter-modal-apply-btn {
    width: 100% !important;
    max-width: 340px !important;
    background-color: #1946A0 !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 5px !important;
    padding: 14px 24px !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    font-family: 'Inter', sans-serif !important;
    cursor: pointer !important;
    transition: background-color 0.2s !important;
    box-sizing: border-box !important;
    text-align: center !important;
}

.filter-modal-apply-btn:hover {
    background-color: #1a3d95 !important;
}

.filter-modal-apply-btn:active {
    background-color: #163882 !important;
}
</style>
