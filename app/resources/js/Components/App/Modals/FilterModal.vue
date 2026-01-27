<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    show: Boolean,
    filters: {
        type: Object,
        default: () => ({
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
    reportNumber: props.filters?.reportNumber || '',
    problemType: props.filters?.problemType || '',
    dateFrom: props.filters?.dateFrom || '',
    dateTo: props.filters?.dateTo || '',
    status: props.filters?.status || '',
})

watch(() => props.show, (newVal) => {
    if (newVal) {
        localFilters.value = {
            reportNumber: props.filters?.reportNumber || '',
            problemType: props.filters?.problemType || '',
            dateFrom: props.filters?.dateFrom || '',
            dateTo: props.filters?.dateTo || '',
            status: props.filters?.status || '',
        }
    }
})

const handleApply = () => {
    emit('apply', { ...localFilters.value })
}

const handleClear = () => {
    localFilters.value = {
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
                    <img src="/icons/close-icon.png" alt="Uždaryti" class="filter-modal-close-icon" />
                </button>
            </div>
            <div class="filter-modal-separator"></div>

            <div class="filter-modal-body">
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
                            <img src="/icons/arrow-icon.png" alt="Dropdown" class="filter-select-arrow" />
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
                        </div>
                        <div class="date-input-wrapper">
                            <input
                                v-model="localFilters.dateTo"
                                type="text"
                                class="filter-input date-input"
                                placeholder="dd/mm/yyyy"
                            />
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

.filter-modal-close-icon {
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

.filter-select-arrow {
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
