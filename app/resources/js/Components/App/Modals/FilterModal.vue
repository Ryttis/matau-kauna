<script setup>
import { computed, ref, watch } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import { PUBLIC_PROBLEM_STATUS_OPTIONS } from '@/constants/domain/problemStatus'

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
    onlyMine: { type: Boolean, default: undefined },
    onlyWatched: { type: Boolean, default: undefined },
    statusCheckboxOptions: { type: Array, default: null },
    dateMin: { type: String, default: '' },
    dateMax: { type: String, default: '' },
})

const emit = defineEmits(['close', 'apply', 'clear', 'onlyMineChange', 'onlyWatchedChange'])

const isPublicMap = () => props.variant === 'publicMap'

const publicMapStatusOptions = computed(() => {
    const o = props.statusCheckboxOptions
    if (Array.isArray(o) && o.length > 0) {
        return o
    }
    return PUBLIC_PROBLEM_STATUS_OPTIONS
})

function getInitialFilters() {
    return {
        reportNumber: props.filters?.reportNumber || '',
        problemType: props.filters?.problemType ?? (isPublicMap() ? 'all' : ''),
        dateFrom: props.filters?.dateFrom || '',
        dateTo: props.filters?.dateTo || '',
        status: props.filters?.status || '',
        statuses: props.filters?.statuses ? [...props.filters.statuses] : [],
    }
}

const localFilters = ref(getInitialFilters())
const localOnlyMine = ref(false)
const localOnlyWatched = ref(false)

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        localFilters.value = getInitialFilters()
        localOnlyMine.value = !!props.onlyMine
        localOnlyWatched.value = !!props.onlyWatched
    }
})

watch(() => props.onlyMine, (v) => {
    localOnlyMine.value = !!v
}, { immediate: true })

watch(() => props.onlyWatched, (v) => {
    localOnlyWatched.value = !!v
}, { immediate: true })

function onOnlyMineChange() {
    emit('onlyMineChange', localOnlyMine.value)
}

function onOnlyWatchedChange() {
    emit('onlyWatchedChange', localOnlyWatched.value)
}

function setStatus(value, checked) {
    const arr = localFilters.value.statuses || []
    if (checked) {
        if (!arr.includes(value)) localFilters.value.statuses = [...arr, value]
    } else {
        localFilters.value.statuses = arr.filter((v) => v !== value)
    }
}

const handleApply = () => {
    const f = localFilters.value
    const payload = {
        reportNumber: f.reportNumber,
        problemType: f.problemType,
        dateFrom: f.dateFrom,
        dateTo: f.dateTo,
    }
    if (isPublicMap()) {
        payload.statuses = [...(f.statuses || [])]
    } else {
        payload.status = f.status
    }
    emit('apply', payload)
}

const handleClear = () => {
    localFilters.value = isPublicMap()
        ? { reportNumber: '', problemType: 'all', dateFrom: '', dateTo: '', statuses: [] }
        : { reportNumber: '', problemType: '', dateFrom: '', dateTo: '', status: '' }
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
        max-width="500px"
        footer-layout="column"
        @close="handleClose"
    >
        <div class="filter-modal-body">
            <label v-if="isPublicMap() && props.onlyMine !== undefined" class="filter-only-mine-label">
                <AppCheckbox v-model="localOnlyMine" @update:model-value="onOnlyMineChange" />
                <span>Rodyti tik mano iškeltas problemas</span>
            </label>
            <label v-if="isPublicMap() && props.onlyWatched !== undefined" class="filter-only-mine-label">
                <AppCheckbox v-model="localOnlyWatched" @update:model-value="onOnlyWatchedChange" />
                <span>Rodyti tik stebimas problemas</span>
            </label>
            <div class="filter-section">
                <AppInput
                    v-model="localFilters.reportNumber"
                    label="Pranešimo numeris"
                    type="text"
                    placeholder="Įrašykite numerį"
                    variant="filter"
                />
            </div>

            <div v-if="isPublicMap()" class="filter-section">
                <label class="filter-label">Būsena</label>
                <div class="filter-status-checkboxes">
                    <label
                        v-for="opt in publicMapStatusOptions"
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

            <div class="filter-section">
                <AppSelect
                    v-model="localFilters.problemType"
                    label="Problemos tipas"
                    placeholder="Pasirinkti kategoriją"
                    :variant="isPublicMap() ? 'blue' : 'white'"
                    :rounded="true"
                >
                    <option v-if="isPublicMap()" value="all">Pasirinkti kategoriją</option>
                    <option v-for="category in (categories || [])" :key="category" :value="category">
                        {{ category }}
                    </option>
                </AppSelect>
            </div>

            <div class="filter-section">
                <label class="filter-label">Data</label>
                <div class="date-inputs">
                    <AppDatePicker
                        v-model="localFilters.dateFrom"
                        placeholder="dd/mm/yyyy"
                        :min-date="props.dateMin || null"
                        :max-date="props.dateMax || null"
                    />
                    <AppDatePicker
                        v-model="localFilters.dateTo"
                        placeholder="dd/mm/yyyy"
                        :min-date="localFilters.dateFrom || props.dateMin || null"
                        :max-date="props.dateMax || null"
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

.filter-only-mine-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1rem;
    font-size: 14px;
    color: #333;
    cursor: pointer;
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
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
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

.date-inputs :deep(.app-date-picker) {
    flex: 1;
    min-width: 0;
}

.date-inputs :deep(.app-date-picker-trigger-wrap) {
    max-width: none;
    width: 100%;
}

.date-inputs :deep(.app-date-picker-trigger) {
    width: 100%;
    height: var(--control-height, 45px);
    min-height: var(--control-height, 45px);
    padding: 0 44px 0 16px;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: 15px;
    background: #fff;
    font-size: 16px;
    font-family: 'Open Sans', sans-serif;
    color: #333;
    box-sizing: border-box;
}

.date-inputs :deep(.app-date-picker-trigger:hover) {
    background: #fff;
    border-color: #ccc;
}

.date-inputs :deep(.app-date-picker-trigger.open),
.date-inputs :deep(.app-date-picker-trigger:focus) {
    border-color: var(--color-primary, #1946A0);
    outline: none;
}

.date-inputs :deep(.app-date-picker-value) {
    color: #333;
}

.date-inputs :deep(.app-date-picker-value.is-placeholder) {
    color: #999;
    font-style: italic;
}

.date-inputs :deep(.app-date-picker-icon) {
    right: 14px;
    width: 18px;
    height: 18px;
}

.filter-modal-footer {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    align-items: center;
    box-sizing: border-box;
}

.filter-modal-footer--narrow .filter-modal-clear-btn,
.filter-modal-footer--narrow .filter-modal-apply-btn {
    width: var(--button-width, 290px);
    height: var(--button-height, 45px);
    min-height: var(--button-height, 45px);
}

.filter-modal-clear-btn,
.filter-modal-apply-btn {
    width: var(--button-width, 290px);
    height: var(--button-height, 45px);
    min-height: var(--button-height, 45px);
    max-width: 100%;
    margin-bottom: 0 !important;
    border-radius: var(--button-border-radius, 15px) !important;
    padding: 0 24px !important;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Open Sans', sans-serif;
    font-weight: 400;
    font-size: 16px;
}

.filter-modal-clear-btn {
    color: var(--color-primary, #1946a0);
}

.filter-modal-apply-btn {
    color: #ffffff;
}

</style>
