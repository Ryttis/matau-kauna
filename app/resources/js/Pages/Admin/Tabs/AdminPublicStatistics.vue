<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'

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

const PERIOD_OPTIONS = [
    { value: 'week', label: 'Savaitė' },
    { value: 'month', label: 'Mėnuo' },
    { value: 'quarter', label: 'Ketvirtis' },
    { value: 'year', label: 'Metai' },
    { value: 'all', label: 'Visas laikotarpis' },
]

const config = ref({
    kpis: ['received_reports', 'avg_resolution_days'],
    default_period: 'year',
    period_options: ['month', 'year', 'all'],
    show_category_filter: true,
})

const selectedKpis = ref([])

function isKpiSelected(value) {
    return selectedKpis.value.includes(value)
}

function toggleKpi(value) {
    const i = selectedKpis.value.indexOf(value)
    if (i === -1) selectedKpis.value.push(value)
    else selectedKpis.value.splice(i, 1)
}

const periodOptionsSelected = ref([])

function isPeriodOptionSelected(value) {
    return periodOptionsSelected.value.includes(value)
}

function togglePeriodOption(value) {
    const i = periodOptionsSelected.value.indexOf(value)
    if (i === -1) periodOptionsSelected.value.push(value)
    else periodOptionsSelected.value.splice(i, 1)
}

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.publicStatisticsConfig)
        config.value = { ...config.value, ...data.config }
        selectedKpis.value = [...(data.config.kpis || [])]
        periodOptionsSelected.value = [...(data.config.period_options || ['year', 'all'])]
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
                default_period: config.value.default_period,
                period_options: periodOptionsSelected.value,
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
                <h3 class="block-title">Laikotarpio parinktys</h3>
                <p class="block-hint">Kuriuos laikotarpių variantus gali rinktis lankytojas.</p>
                <div class="checkbox-list">
                    <label v-for="opt in PERIOD_OPTIONS" :key="opt.value" class="checkbox-row">
                        <AppCheckbox :model-value="isPeriodOptionSelected(opt.value)" @update:model-value="togglePeriodOption(opt.value)" />
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
            </div>

            <div class="settings-block">
                <h3 class="block-title">Numatytasis laikotarpis</h3>
                <select v-model="config.default_period" class="select">
                    <option v-for="opt in PERIOD_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
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
</style>
