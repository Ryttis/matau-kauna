<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import PublicFilterSelect from '@/Components/Guest/PublicFilterSelect.vue'
import CategoryStatsBlock from '@/Components/Statistics/CategoryStatsBlock.vue'

const PERIOD_LABELS = {
    week: 'Paskutinė savaitė',
    month: 'Paskutinis mėnuo',
    quarter: 'Paskutinis ketvirtis',
    year: 'Paskutiniai metai',
    all: 'Visas laikotarpis',
}

const config = ref(null)
const data = ref(null)
const categories = ref([])
const loading = ref(true)
const errorMessage = ref('')

const periodFilter = ref(null)
const categoryFilter = ref('all')
const syncingPeriodFromServer = ref(false)
let isFirstPublicStatsFetch = true

const periodOptions = computed(() => {
    const opts = config.value?.period_options ?? ['year', 'all']
    return opts.map(v => ({ value: v, label: PERIOD_LABELS[v] ?? v }))
})

const categoryOptions = computed(() => {
    const list = [{ value: 'all', label: 'Kategorija: Visos' }]
    ;(categories.value || []).forEach(c => {
        list.push({ value: c, label: `Kategorija: ${c}` })
    })
    return list
})

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

async function fetchData() {
    loading.value = true
    errorMessage.value = ''
    try {
        const params = new URLSearchParams()
        if (!isFirstPublicStatsFetch && periodFilter.value != null) {
            params.set('period', periodFilter.value)
        }
        if (categoryFilter.value && categoryFilter.value !== 'all') params.set('category', categoryFilter.value)
        const url = route('public.statistics.data') + (params.toString() ? '?' + params.toString() : '')
        const { data: res } = await axios.get(url)
        config.value = res.config
        data.value = res.data
        categories.value = res.categories ?? []
        const defaultPeriod = res.config?.default_period ?? 'year'
        const allowedPeriods = res.config?.period_options ?? ['year', 'all']

        syncingPeriodFromServer.value = true
        if (isFirstPublicStatsFetch) {
            periodFilter.value = allowedPeriods.includes(defaultPeriod) ? defaultPeriod : (allowedPeriods[0] ?? 'year')
            isFirstPublicStatsFetch = false
        } else if (!allowedPeriods.includes(periodFilter.value)) {
            periodFilter.value = allowedPeriods.includes(defaultPeriod) ? defaultPeriod : (allowedPeriods[0] ?? 'year')
        }
        await nextTick()
        syncingPeriodFromServer.value = false
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
watch([periodFilter, categoryFilter], () => {
    if (syncingPeriodFromServer.value) return
    fetchData()
})
</script>

<template>
    <Head title="Statistika – Kauno problemos" />
    <div class="landing public-statistics-page">
        <GuestHeader active-link="statistics" />
        <main class="public-statistics-main">
            <div class="public-statistics-inner">
                <div class="filters-section">
                    <PublicFilterSelect
                        v-if="periodFilter != null"
                        v-model="periodFilter"
                        :options="periodOptions"
                    />
                    <PublicFilterSelect
                        v-if="showCategoryFilter"
                        v-model="categoryFilter"
                        :options="categoryOptions"
                    />
                </div>

                <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
                <div v-if="loading" class="loading">Kraunama...</div>

                <template v-else>
                    <div class="stats-grid">
                        <div v-if="kpiCards.length" class="stats-card kpi-block">
                            <div class="kpi-row">
                                <div
                                    v-for="(kpi, index) in kpiCards"
                                    :key="index"
                                    class="kpi-card"
                                >
                                    <h3 class="kpi-title">{{ kpi.title }}</h3>
                                    <p class="kpi-value">{{ kpi.value }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="showMonthlyChart" class="stats-card chart-card">
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
                                            <span class="bar-label">{{ month.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="showProblematicCategories" class="stats-card category-card">
                            <CategoryStatsBlock
                                title="Probleminės kategorijos"
                                :categories="(data && data.problematicCategories) ? data.problematicCategories : []"
                            />
                        </div>

                        <div v-if="showEffectivelyResolved" class="stats-card category-card">
                            <CategoryStatsBlock
                                title="Efektyviausiai sprendžiamos problemos"
                                :categories="(data && data.effectivelyResolved) ? data.effectivelyResolved : []"
                            />
                        </div>
                    </div>
                </template>
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

.filters-section {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.75rem 1rem;
    margin-bottom: 1.5rem;
}

.error {
    color: #b91c1c;
    margin: 0 0 1rem 0;
}

.loading {
    color: #666;
    margin: 0 0 1rem 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto 1fr;
    gap: 1.25rem;
    align-items: stretch;
}

@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: 1fr;
        grid-template-rows: auto;
    }
}

.stats-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-width: 0;
}

.kpi-block {
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
}

.kpi-block .kpi-row {
    flex: 1;
    min-height: 260px;
}

.category-card {
    overflow: hidden;
    min-height: 200px;
}

.category-card :deep(.category-stats-block) {
    box-shadow: none;
    height: 100%;
}

.kpi-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.kpi-card {
    padding: 1.25rem;
}

.kpi-title {
    font-size: 0.875rem;
    color: #666;
    margin: 0 0 0.5rem 0;
    font-weight: 400;
}

.kpi-value {
    font-size: 1.75rem;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.chart-card {
    padding: 1.25rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.chart-container {
    height: 260px;
    padding: 0.5rem 0;
}

.bar-chart {
    display: flex;
    height: 100%;
    align-items: flex-end;
}

.chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding-right: 0.5rem;
    font-size: 0.75rem;
    color: #666;
    min-width: 28px;
}

.chart-bars {
    display: flex;
    flex: 1;
    align-items: flex-end;
    height: 100%;
}

.bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    min-width: 0;
    border-left: 1px solid #e8e8e8;
}

.bar-wrapper:first-child {
    border-left: none;
}

.bar {
    width: 100%;
    max-width: 24px;
    background: #64b5f6;
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    transition: height 0.2s ease;
}

.bar-label {
    margin-top: 6px;
    font-size: 0.75rem;
    color: #666;
}

@media (max-width: 767px) {
    .public-statistics-inner {
        padding-left: max(1rem, env(safe-area-inset-left));
        padding-right: max(1rem, env(safe-area-inset-right));
    }

    .filters-section {
        flex-direction: column;
        align-items: stretch;
    }

    .filters-section :deep(.public-filter-select) {
        max-width: none;
        flex: 1 1 auto;
    }
}
</style>
