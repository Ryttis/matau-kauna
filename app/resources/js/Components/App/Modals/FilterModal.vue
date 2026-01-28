<script setup>
import { ref, watch } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'

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
    <BaseModal
        :show="show"
        title="Filtruoti"
        :max-width="'500px'"
        @close="handleClose"
    >
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

        <template #footer>
            <div style="display: flex; flex-direction: column; gap: 10px; width: 100%; align-items: center;">
                <button class="filter-modal-clear-btn" @click="handleClear">
                    Išvalyti filtrą
                </button>
                <button class="filter-modal-apply-btn" @click="handleApply">
                    Filtruoti
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<style scoped>
.filter-modal-body {
    padding: 0;
}

:deep(.modal-body) {
    padding: 30px;
}

.filter-section {
    margin-bottom: 20px;
}

.filter-section:last-child {
    margin-bottom: 0;
}

.filter-label {
    display: block;
    margin-bottom: 12px;
    font-size: 16px;
    font-weight: 600;
    color: #333333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
}

.filter-input {
    width: 100%;
    height: 40px;
    padding: 0 15px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333333;
    background-color: #f5f5f5;
    box-sizing: border-box;
}

.filter-input:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #ffffff;
}

.filter-input::placeholder {
    color: #999999;
    font-style: italic;
}

.filter-select-wrapper {
    position: relative;
    width: 100%;
}

.filter-select {
    width: 100%;
    height: 48px;
    padding: 0 50px 0 15px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #ffffff;
    background-color: #1946A0;
    cursor: pointer;
    box-sizing: border-box;
    appearance: none;
    text-align: left;
}

.filter-select-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-select-arrow {
    display: block;
    width: 16px;
    height: 16px;
}

.filter-select option {
    background-color: #ffffff;
    color: #333333;
}

.filter-select:focus {
    outline: none;
    border-color: #1946A0;
}

.date-inputs {
    display: flex;
    gap: 10px;
}

.date-input-wrapper {
    position: relative;
    flex: 1;
}

.date-input {
    width: 100%;
    padding-right: 45px;
}

.date-input::-webkit-calendar-picker-indicator {
    display: none;
    -webkit-appearance: none;
}

.date-input::-webkit-inner-spin-button,
.date-input::-webkit-outer-spin-button {
    display: none;
}



.filter-modal-clear-btn {
    width: 100%;
    max-width: 340px;
    background-color: #ffffff;
    color: #1946A0;
    border: 1px solid #1946A0;
    border-radius: 5px;
    padding: 14px 24px;
    font-size: 16px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    box-sizing: border-box;
    text-align: center;
}

.filter-modal-clear-btn:hover {
    background-color: #f5f5f5;
}

.filter-modal-apply-btn {
    width: 100%;
    max-width: 340px;
    background-color: #1946A0;
    color: #ffffff;
    border: none;
    border-radius: 5px;
    padding: 14px 24px;
    font-size: 16px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: background-color 0.2s;
    box-sizing: border-box;
    text-align: center;
}

.filter-modal-apply-btn:hover {
    background-color: #1a3d95;
}

.filter-modal-apply-btn:active {
    background-color: #163882;
}
</style>
