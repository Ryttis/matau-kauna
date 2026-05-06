<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'

const PRESET_OPTIONS = [
    { value: '', label: '-' },
    { value: 'month', label: 'Mėnuo' },
    { value: 'quarter', label: '3 mėnesiai' },
    { value: 'half', label: 'Pusmetis' },
    { value: 'year', label: 'Metai' },
]

function todayIso() {
    return new Date().toISOString().slice(0, 10)
}

function presetToDates(preset) {
    const now = new Date()
    const to = todayIso()
    const start = new Date(now)
    if (preset === 'month') start.setMonth(start.getMonth() - 1)
    else if (preset === 'quarter') start.setMonth(start.getMonth() - 3)
    else if (preset === 'half') start.setMonth(start.getMonth() - 6)
    else if (preset === 'year') start.setFullYear(start.getFullYear() - 1)
    else return { from: '', to: '' }
    return { from: start.toISOString().slice(0, 10), to }
}

const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const showSuccessModal = ref(false)

const KPI_OPTIONS = [
    { value: 'received_reports', label: 'Gauti pranešimai' },
    { value: 'avg_resolution_days', label: 'Vidutinis sprendimo laikas' },
    { value: 'resolved_problems', label: 'Išspręstos problemos procentais' },
    { value: 'resolved_on_time', label: 'Laiku išspręstos procentais' },
    { value: 'monthly_reports', label: 'Mėnesiniai pranešimų skaičiai' },
    { value: 'problematic_categories', label: 'Probleminės kategorijos' },
    { value: 'effectively_resolved', label: 'Efektyviausiai sprendžiamos' },
]

const config = ref({
    kpis: ['received_reports', 'avg_resolution_days'],
    date_from: null,
    date_to: null,
    show_category_filter: true,
})

const selectedKpis = ref([])
const dateFrom = ref('')
const dateTo = ref('')
const presetFilter = ref('')
let applyingPreset = false

function applyPreset(value) {
    presetFilter.value = value
    if (!value) return
    applyingPreset = true
    const { from, to } = presetToDates(value)
    dateFrom.value = from
    dateTo.value = to
    queueMicrotask(() => { applyingPreset = false })
}

function onDateChanged() {
    if (applyingPreset) return
    if (presetFilter.value) presetFilter.value = ''
}

function isKpiSelected(value) {
    return selectedKpis.value.includes(value)
}

function toggleKpi(value) {
    const i = selectedKpis.value.indexOf(value)
    if (i === -1) selectedKpis.value.push(value)
    else selectedKpis.value.splice(i, 1)
}

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.publicStatisticsConfig)
        config.value = { ...config.value, ...data.config }
        selectedKpis.value = [...(data.config.kpis || [])]
        dateFrom.value = data.config.date_from ?? ''
        dateTo.value = data.config.date_to ?? ''
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko užkrauti.'
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.put(adminApi.publicStatisticsConfig, {
            config: {
                kpis: selectedKpis.value,
                date_from: dateFrom.value || null,
                date_to: dateTo.value || null,
                show_category_filter: config.value.show_category_filter,
            },
        })
        successMessage.value = 'Nustatymai išsaugoti.'
        showSuccessModal.value = true
        await load()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko išsaugoti.'
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="admin-public-statistics">
        <div class="admin-tab-intro">
            <h2 class="admin-tab-intro__title">Vieša statistika</h2>
            <p class="admin-tab-intro__desc">
                Nustatykite rodiklius ir laikotarpius, kuriuos lankytojas mato viešame statistikos puslapyje.
            </p>
        </div>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else>
            <div class="settings-block">
                <h3 class="block-title">Rodikliai</h3>
                <p class="block-hint">Pasirinkite, ką rodyti viešame statistikos puslapyje.</p>
                <div class="checkbox-list">
                    <label v-for="opt in KPI_OPTIONS" :key="opt.value" class="checkbox-row">
                        <AppCheckbox :model-value="isKpiSelected(opt.value)" @update:model-value="toggleKpi(opt.value)" />
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
            </div>

            <div class="settings-block">
                <h3 class="block-title">Laikotarpis</h3>
                <p class="block-hint">Datų rėžis, kurio statistika bus rodoma viešai. Galite naudoti pagalbinį pasirinkimą arba įvesti datas rankomis. Palikite tuščius laukus, kad būtų rodomi visų laikų duomenys.</p>
                <div class="preset-row">
                    <AppSelect
                        :model-value="presetFilter"
                        label="Greitas pasirinkimas"
                        variant="white"
                        :placeholder="''"
                        @update:model-value="applyPreset"
                    >
                        <option v-for="opt in PRESET_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </AppSelect>
                </div>
                <div class="date-range-row">
                    <AppDatePicker
                        :model-value="dateFrom"
                        label="Data nuo"
                        placeholder="Pasirinkite datą"
                        @update:model-value="(v) => { dateFrom = v; onDateChanged() }"
                    />
                    <AppDatePicker
                        :model-value="dateTo"
                        label="Data iki"
                        placeholder="Pasirinkite datą"
                        :min-date="dateFrom || undefined"
                        @update:model-value="(v) => { dateTo = v; onDateChanged() }"
                    />
                </div>
            </div>

            <div class="settings-block">
                <label class="checkbox-row">
                    <AppCheckbox v-model="config.show_category_filter" />
                    <span>Rodyti kategorijos filtrą</span>
                </label>
            </div>

            <AppButton
                text="Išsaugoti"
                variant="primary"
                as-button
                :disabled="saving"
                @click="save"
            />
        </template>
    </div>
</template>

<style scoped>
.admin-public-statistics {
    width: 100%;
    max-width: 640px;
    box-sizing: border-box;
}
.error {
    color: #b91c1c;
    font-size: 14px;
    margin: 0 0 16px 0;
}
.loading {
    color: #666;
    font-size: 14px;
}
.settings-block {
    margin-bottom: 24px;
}
.block-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 4px 0;
    padding: 0;
    color: #1a1a1a;
}
.block-hint {
    font-size: 14px;
    color: #555;
    line-height: 1.35;
    margin: 0 0 12px 0;
}
.checkbox-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}
.select {
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 5px;
    min-width: 200px;
}
.date-range-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.date-range-row :deep(.app-date-picker) {
    margin-bottom: 0;
}
.date-range-row > * {
    flex: 1 1 220px;
    min-width: 200px;
    max-width: 280px;
}
.preset-row {
    margin-bottom: 16px;
    max-width: 280px;
}
.preset-row :deep(.app-select-group) {
    margin-bottom: 0;
}
</style>
