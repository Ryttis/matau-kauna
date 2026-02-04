<script setup>
import { ref, watch } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppSelect from '@/Components/App/AppSelect.vue'

const PUBLIC_STATUS_OPTIONS = [
    { value: 'nauja', label: 'Nauja' },
    { value: 'nagrinejama', label: 'Nagrinėjama' },
    { value: 'isspresta', label: 'Išspręsta' },
]

const props = defineProps({
    show: Boolean,
    variant: {
        type: String,
        default: 'dashboard',
        validator: (v) => ['dashboard', 'publicMap'].includes(v),
    },
    filters: {
        type: Object,
        default: () => ({
            reportNumber: '',
            problemType: '',
            dateFrom: '',
            dateTo: '',
            status: '',
            statuses: [],
        }),
    },
    categories: Array,
    allStatusOptions: Array,
})

const emit = defineEmits(['close', 'apply', 'clear'])

const isPublicMap = () => props.variant === 'publicMap'

const localFilters = ref({
    reportNumber: props.filters?.reportNumber || '',
    problemType: props.filters?.problemType ?? (isPublicMap() ? 'all' : ''),
    dateFrom: props.filters?.dateFrom || '',
    dateTo: props.filters?.dateTo || '',
    status: props.filters?.status || '',
    statuses: props.filters?.statuses ? [...props.filters.statuses] : [],
})

watch(() => props.show, (newVal) => {
    if (newVal) {
        localFilters.value = {
            reportNumber: props.filters?.reportNumber || '',
            problemType: props.filters?.problemType ?? (isPublicMap() ? 'all' : ''),
            dateFrom: props.filters?.dateFrom || '',
            dateTo: props.filters?.dateTo || '',
            status: props.filters?.status || '',
            statuses: props.filters?.statuses ? [...props.filters.statuses] : [],
        }
    }
})

function setStatus(value, checked) {
    const arr = localFilters.value.statuses || []
    if (checked) {
        if (!arr.includes(value)) localFilters.value.statuses = [...arr, value]
    } else {
        localFilters.value.statuses = arr.filter((v) => v !== value)
    }
}

const handleApply = () => {
    if (isPublicMap()) {
        emit('apply', {
            statuses: [...(localFilters.value.statuses || [])],
            problemType: localFilters.value.problemType,
            dateFrom: localFilters.value.dateFrom,
            dateTo: localFilters.value.dateTo,
        })
    } else {
        emit('apply', {
            reportNumber: localFilters.value.reportNumber,
            problemType: localFilters.value.problemType,
            dateFrom: localFilters.value.dateFrom,
            dateTo: localFilters.value.dateTo,
            status: localFilters.value.status,
        })
    }
}

const handleClear = () => {
    if (isPublicMap()) {
        localFilters.value = {
            problemType: 'all',
            dateFrom: '',
            dateTo: '',
            statuses: [],
        }
    } else {
        localFilters.value = {
            reportNumber: '',
            problemType: '',
            dateFrom: '',
            dateTo: '',
            status: '',
        }
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
            <!-- Dashboard: Pranešimo numeris -->
            <div v-if="!isPublicMap()" class="filter-section">
                <AppInput
                    v-model="localFilters.reportNumber"
                    label="Pranešimo numeris"
                    type="text"
                    placeholder="Įrašykite numerį"
                    variant="filter"
                />
            </div>

            <!-- PublicMap: Būsena -->
            <div v-else class="filter-section">
                <label class="filter-label">Būsena</label>
                <div class="filter-status-checkboxes">
                    <label
                        v-for="opt in PUBLIC_STATUS_OPTIONS"
                        :key="opt.value"
                        class="filter-checkbox-label"
                    >
                        <AppCheckbox
                            :model-value="(localFilters.statuses || []).includes(opt.value)"
                            @update:model-value="(checked) => setStatus(opt.value, checked)"
                        />
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
            </div>

            <!-- Problemos tipas (category) -->
            <div class="filter-section">
                <AppSelect
                    v-model="localFilters.problemType"
                    label="Problemos tipas"
                    placeholder="Pasirinkti kategorija"
                    :variant="isPublicMap() ? 'blue' : 'white'"
                >
                    <option v-if="isPublicMap()" value="all">Pasirinkti kategorija</option>
                    <option v-for="category in (categories || [])" :key="category" :value="category">
                        {{ category }}
                    </option>
                </AppSelect>
            </div>

            <!-- Data -->
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
            <div class="filter-modal-footer" :class="{ 'filter-modal-footer--narrow': isPublicMap() }">
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
    padding: 16px 20px 20px;
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

.filter-status-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.filter-checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 16px;
    color: #333;
}

.date-inputs {
    display: flex;
    gap: 10px;
}

.date-inputs :deep(.app-input-group) {
    flex: 1;
}

.filter-modal-footer {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    align-items: center;
}

.filter-modal-footer--narrow .filter-modal-clear-btn,
.filter-modal-footer--narrow .filter-modal-apply-btn {
    max-width: 260px;
}

.filter-modal-clear-btn,
.filter-modal-apply-btn {
    width: 100%;
    max-width: 340px;
    margin-bottom: 0 !important;
}
</style>
