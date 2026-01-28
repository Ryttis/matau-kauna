<script setup>
import { ref, watch } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppSelect from '@/Components/App/AppSelect.vue'

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
                    <AppInput
                        v-model="localFilters.reportNumber"
                        label="Pranešimo numeris"
                        type="text"
                        placeholder="Įrašykite numerį"
                        variant="filter"
                    />
                </div>

                <div class="filter-section">
                    <AppSelect
                        v-model="localFilters.problemType"
                        label="Problemos tipas"
                        placeholder="Pasirinkti kategorija"
                    >
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </AppSelect>
                </div>

                <div class="filter-section">
                    <label class="filter-label">Data</label>
                    <div class="date-inputs">
                        <AppInput
                            v-model="localFilters.dateFrom"
                            type="text"
                            placeholder="dd/mm/yyyy"
                            variant="filter"
                        />
                        <AppInput
                            v-model="localFilters.dateTo"
                            type="text"
                            placeholder="dd/mm/yyyy"
                            variant="filter"
                        />
                    </div>
                </div>
        </div>

        <template #footer>
            <div style="display: flex; flex-direction: column; gap: 10px; width: 100%; align-items: center;">
                <AppButton
                    text="Išvalyti filtrą"
                    variant="secondary"
                    as-button
                    @click="handleClear"
                    class="filter-modal-clear-btn"
                />
                <AppButton
                    text="Filtruoti"
                    variant="primary"
                    as-button
                    @click="handleApply"
                    class="filter-modal-apply-btn"
                />
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

.date-inputs {
    display: flex;
    gap: 10px;
}

.filter-modal-clear-btn,
.filter-modal-apply-btn {
    width: 100%;
    max-width: 340px;
    margin-bottom: 0 !important;
}
</style>
