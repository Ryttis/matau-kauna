<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import CategoryStatsBlock from '@/Components/Statistics/CategoryStatsBlock.vue'
import '@/Components/Statistics/statistics-page.css'

const LT_MONTH_ABBR = ['Sau', 'Vas', 'Kov', 'Bal', 'Geg', 'Birž', 'Liep', 'Rugp', 'Rugs', 'Spal', 'Lapkr', 'Gruod']

const config = ref(null)
const effective = ref({ date_from: null, date_to: null })
const data = ref(null)
const categories = ref([])
const loading = ref(true)
const errorMessage = ref('')

const categoryFilter = ref('all')
const userDateFrom = ref('')
const userDateTo = ref('')
let isFirstFetch = true

const categoryOptions = computed(() => {
    const list = [{ value: 'all', label: 'Visos' }]
    ;(categories.value || []).forEach(c => {
        list.push({ value: c, label: c })
    })
    return list
})

const periodLabel = computed(() => {
    const from = effective.value?.date_from || config.value?.date_from
    const to = effective.value?.date_to || config.value?.date_to
    const fmt = (s) => {
        if (!s) return ''
        const d = new Date(s)
        if (Number.isNaN(d.getTime())) return s
        return d.toLocaleDateString('lt-LT')
    }
    if (from && to) return `${fmt(from)} – ${fmt(to)}`
    if (from) return `Nuo ${fmt(from)}`
    if (to) return `Iki ${fmt(to)}`
    return 'Visas laikotarpis'
})

const adminMinDate = computed(() => config.value?.date_from || undefined)
const adminMaxDate = computed(() => config.value?.date_to || undefined)
const datePickerMinFor = (which) => {
    if (which === 'to' && userDateFrom.value) return userDateFrom.value
    return adminMinDate.value
}
const datePickerMaxFor = (which) => {
    if (which === 'from' && userDateTo.value) return userDateTo.value
    return adminMaxDate.value
}

function formatChartLabel(ym) {
    if (!ym) return ''
    const parts = String(ym).split('-')
    if (parts.length < 2) return ym
    const year = parts[0]
    const month = parseInt(parts[1], 10)
    if (Number.isNaN(month) || month < 1 || month > 12) return ym
    const yy = year.slice(-2)
    return `${LT_MONTH_ABBR[month - 1]} ${yy}`
}

const showCategoryFilter = computed(() => config.value?.show_category_filter !== false)

const kpiCards = computed(() => {
    const kpis = config.value?.kpis ?? []
    const d = data.value || {}
    const items = []
    if (kpis.includes('received_reports')) {
        items.push({ title: 'Gauti pranešimai', value: (d.receivedReports ?? 0).toLocaleString() })
    }
    if (kpis.includes('avg_resolution_days')) {
        items.push({ title: 'Vidutinis sprendimo laikas', value: `${d.averageResolutionTime ?? 0} d.` })
    }
    if (kpis.includes('resolved_problems')) {
        items.push({ title: 'Išspręstos problemos', value: `${d.resolvedProblems ?? 0}%` })
    }
    if (kpis.includes('resolved_on_time')) {
        items.push({ title: 'Laiku išspręstos', value: `${d.resolvedOnTime ?? 0}%` })
    }
    return items
})

const monthlyReports = computed(() => data.value?.monthlyReports ?? [])
const chartMax = computed(() => {
    const vals = monthlyReports.value.map(r => r.value)
    return Math.max(1, ...vals)
})

const showMonthlyChart = computed(() => (config.value?.kpis ?? []).includes('monthly_reports'))
const showProblematicCategories = computed(() => (config.value?.kpis ?? []).includes('problematic_categories'))
const showEffectivelyResolved = computed(() => (config.value?.kpis ?? []).includes('effectively_resolved'))
const showCategorySection = computed(() => showProblematicCategories.value || showEffectivelyResolved.value)

async function fetchData() {
    loading.value = true
    errorMessage.value = ''
    try {
        const params = new URLSearchParams()
        if (categoryFilter.value && categoryFilter.value !== 'all') params.set('category', categoryFilter.value)
        if (!isFirstFetch && userDateFrom.value) params.set('date_from', userDateFrom.value)
        if (!isFirstFetch && userDateTo.value) params.set('date_to', userDateTo.value)
        const url = route('public.statistics.data') + (params.toString() ? '?' + params.toString() : '')
        const { data: res } = await axios.get(url)
        config.value = res.config
        effective.value = res.effective ?? { date_from: null, date_to: null }
        data.value = res.data
        categories.value = res.categories ?? []
        if (isFirstFetch) {
            userDateFrom.value = res.effective?.date_from ?? ''
            userDateTo.value = res.effective?.date_to ?? ''
            isFirstFetch = false
        }
    } catch {
        errorMessage.value = 'Nepavyko užkrauti statistikos.'
    } finally {
        loading.value = false
    }
}

function barHeight(value) {
    if (!chartMax.value) return 0
    return (value / chartMax.value) * 100
}

onMounted(fetchData)
watch([categoryFilter, userDateFrom, userDateTo], () => {
    if (isFirstFetch) return
    fetchData()
})
</script>

<template>
    <Head title="Statistika – Kauno problemos" />
    <div class="landing public-statistics-page">
        <GuestHeader active-link="statistics" />
        <main class="public-statistics-main">
            <div class="public-statistics-inner">
                <div class="statistics-page public-statistics-content">
                    <div class="page-header">
                        <div class="page-header-left">
                            <h1 class="page-title">Statistika</h1>
                            <p class="page-desc">
                                Bendra informacija apie pranešimus ir jų sprendimo eigą.
                                <span class="public-statistics-period">Laikotarpis: {{ periodLabel }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="public-statistics-filters">
                        <AppDatePicker
                            v-model="userDateFrom"
                            label="Data nuo"
                            placeholder="Pasirinkite datą"
                            :min-date="datePickerMinFor('from')"
                            :max-date="datePickerMaxFor('from')"
                        />
                        <AppDatePicker
                            v-model="userDateTo"
                            label="Data iki"
                            placeholder="Pasirinkite datą"
                            :min-date="datePickerMinFor('to')"
                            :max-date="datePickerMaxFor('to')"
                        />
                        <AppSelect
                            v-if="showCategoryFilter"
                            v-model="categoryFilter"
                            label="Kategorija"
                            variant="white"
                        >
                            <option
                                v-for="option in categoryOptions"
                                :key="String(option.value)"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </AppSelect>
                    </div>

                    <p v-if="errorMessage" class="public-statistics-error">{{ errorMessage }}</p>
                    <div v-if="loading" class="public-statistics-loading">Kraunama...</div>

                    <template v-else>
                        <div v-if="kpiCards.length" class="kpi-grid public-statistics-kpi-grid">
                            <div
                                v-for="(kpi, index) in kpiCards"
                                :key="index"
                                class="kpi-card"
                            >
                                <h3 class="kpi-title">{{ kpi.title }}</h3>
                                <p class="kpi-value">{{ kpi.value }}</p>
                                <div class="kpi-mini-chart">
                                    <svg width="100" height="20" viewBox="0 0 100 20">
                                        <polyline
                                            points="0,15 20,12 40,10 60,8 80,5 100,3"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div v-if="showCategorySection" class="category-stats-grid">
                            <CategoryStatsBlock
                                v-if="showProblematicCategories"
                                title="Probleminės kategorijos"
                                :categories="(data && data.problematicCategories) ? data.problematicCategories : []"
                            />
                            <CategoryStatsBlock
                                v-if="showEffectivelyResolved"
                                title="Efektyviausiai sprendžiamos problemos"
                                :categories="(data && data.effectivelyResolved) ? data.effectivelyResolved : []"
                            />
                        </div>

                        <div v-if="showMonthlyChart" class="chart-card public-statistics-chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Pranešimų skaičius</h3>
                            </div>
                            <div class="chart-container">
                                <div class="bar-chart">
                                    <div class="chart-y-axis">
                                        <span>{{ chartMax }}</span>
                                        <span>{{ Math.round(chartMax * 0.66) }}</span>
                                        <span>{{ Math.round(chartMax * 0.33) }}</span>
                                        <span>0</span>
                                    </div>
                                    <div class="chart-bars">
                                        <div
                                            v-for="(month, i) in monthlyReports"
                                            :key="i"
                                            class="bar-wrapper"
                                        >
                                            <div
                                                class="bar"
                                                :style="{ height: barHeight(month.value) + '%' }"
                                            />
                                            <span class="bar-label">{{ formatChartLabel(month.label) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.public-statistics-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f5f5;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.public-statistics-main {
    flex: 1;
    padding: 1.5rem 0 2.5rem;
}

.public-statistics-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
    box-sizing: border-box;
}

.public-statistics-content {
    min-height: 0;
}

.public-statistics-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}

.public-statistics-filters > * {
    flex: 1 1 200px;
    min-width: 180px;
    max-width: 240px;
}

.public-statistics-filters :deep(.app-date-picker),
.public-statistics-filters :deep(.app-select-group) {
    margin-bottom: 0;
}

.public-statistics-period {
    display: inline-block;
    margin-left: 0.5rem;
    color: #555;
}

.public-statistics-content :deep(.bar-label) {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: normal;
    font-size: 11px;
}

.public-statistics-content :deep(.public-statistics-kpi-grid) {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 1.25rem;
}

.public-statistics-content :deep(.public-statistics-chart-card) {
    margin-top: 1.25rem;
}

.public-statistics-content :deep(.public-statistics-chart-card .chart-container) {
    height: 320px;
}

@media (max-width: 1100px) {
    .public-statistics-content :deep(.public-statistics-kpi-grid) {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .public-statistics-content :deep(.public-statistics-kpi-grid) {
        grid-template-columns: 1fr;
    }
}

.public-statistics-error {
    color: #b91c1c;
    margin: 0 0 1rem 0;
}

.public-statistics-loading {
    color: #666;
    margin: 0 0 1rem 0;
}

@media (max-width: 767px) {
    .public-statistics-inner {
        padding-left: max(1rem, env(safe-area-inset-left));
        padding-right: max(1rem, env(safe-area-inset-right));
    }
}
</style>
