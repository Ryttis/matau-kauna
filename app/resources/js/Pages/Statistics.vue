<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import CategoryStatsBlock from '@/Components/Statistics/CategoryStatsBlock.vue'
import EmployeeActivityBlock from '@/Components/Statistics/EmployeeActivityBlock.vue'
import InstitutionResultsBlock from '@/Components/Statistics/InstitutionResultsBlock.vue'
import { ICON_DOWNLOAD } from '@/constants/icons'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    institutions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ period: 'all', institution: 'all', category: 'all' }) },
    activeUsers: { type: Object, default: () => ({ current: 0, total: 0 }) },
    receivedReports: { type: Number, default: 0 },
    averageResolutionTime: { type: Number, default: 0 },
    resolvedProblems: { type: Number, default: 0 },
    resolvedOnTime: { type: Number, default: 0 },
    improvement: { type: Number, default: 0 },
    monthlyReports: { type: Array, default: () => [] },
    problematicCategories: { type: Array, default: () => [] },
    effectivelyResolved: { type: Array, default: () => [] },
    employeeActivity: { type: Array, default: () => [] },
    institutionResults: { type: Array, default: () => [] },
})

const periodFilter = ref(props.filters.period || 'all')
const institutionFilter = ref(props.filters.institution ?? 'all')
const categoryFilter = ref(props.filters.category ?? 'all')

const periodOptions = computed(() => [
    { value: 'all', label: 'Laikotarpis: Visas laikotarpis' },
    { value: 'week', label: 'Laikotarpis: Paskutinė savaitė' },
    { value: 'month', label: 'Laikotarpis: Paskutinis mėnuo' },
    { value: 'quarter', label: 'Laikotarpis: Paskutinis ketvirtis' },
    { value: 'year', label: 'Laikotarpis: Paskutiniai metai' },
])

const institutionOptions = computed(() => {
    const options = [{ value: 'all', label: 'Įstaigos: Visos' }]
    ;(props.institutions || []).forEach(inst => {
        options.push({ value: String(inst.id), label: `Įstaigos: ${inst.name}` })
    })
    return options
})

const categoryOptions = computed(() => {
    const options = [{ value: 'all', label: 'Kategorija: Visos' }]
    ;(props.categories || []).forEach(cat => {
        options.push({ value: cat, label: `Kategorija: ${cat}` })
    })
    return options
})

const chartMax = computed(() => {
    const vals = (props.monthlyReports || []).map(r => r.value)
    return Math.max(1, ...vals)
})

const applyFilters = () => {
    router.get(route('statistics'), {
        period: periodFilter.value === 'all' ? null : periodFilter.value,
        institution: institutionFilter.value === 'all' ? null : institutionFilter.value,
        category: categoryFilter.value === 'all' ? null : categoryFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([periodFilter, institutionFilter, categoryFilter], () => {
    applyFilters()
})

const exportQueryParams = computed(() => {
    const p = new URLSearchParams()
    if (periodFilter.value && periodFilter.value !== 'all') p.set('period', periodFilter.value)
    if (institutionFilter.value && institutionFilter.value !== 'all') p.set('institution', institutionFilter.value)
    if (categoryFilter.value && categoryFilter.value !== 'all') p.set('category', categoryFilter.value)
    return p.toString()
})

const downloadReport = () => {
    const url = route('statistics.export') + (exportQueryParams.value ? '?' + exportQueryParams.value : '')
    window.location.href = url
}
</script>

<template>
    <Head title="Statistika" />

    <AuthenticatedLayout>
        <div class="statistics-page">
            <div class="page-header">
                <h1 class="page-title">Statistika</h1>
                <button @click="downloadReport" class="download-header-btn">
                    <img :src="ICON_DOWNLOAD" alt="Download" class="download-icon" />
                    <span>Atsisiųsti</span>
                </button>
            </div>

            <div class="filters-section">
                <AppSelect
                    v-model="periodFilter"
                    variant="white"
                >
                    <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect
                    v-model="institutionFilter"
                    variant="white"
                >
                    <option v-for="option in institutionOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect
                    v-model="categoryFilter"
                    variant="white"
                >
                    <option v-for="option in categoryOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>
            </div>

            <div class="kpi-chart-row">
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <h3 class="kpi-title">Aktyvūs naudotojai</h3>
                        <p class="kpi-value">{{ props.activeUsers.current }}/{{ props.activeUsers.total }}</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Gauti pranešimai</h3>
                        <p class="kpi-value">{{ props.receivedReports.toLocaleString() }}</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Vidutinis sprendimo laikas</h3>
                        <p class="kpi-value">{{ props.averageResolutionTime || 0 }} d.</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Išspręstos problemos</h3>
                        <p class="kpi-value">{{ props.resolvedProblems || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Laiku išspręstos</h3>
                        <p class="kpi-value">{{ props.resolvedOnTime || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Pagerėjimas</h3>
                        <p class="kpi-value">{{ props.improvement >= 0 ? '+' : '' }}{{ props.improvement || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Pranešimų skaičius</h3>
                        <AppSelect
                            model-value="months"
                            class="chart-filter"
                        >
                            <option value="months">Mėnesiai</option>
                        </AppSelect>
                    </div>
                    <div class="chart-container">
                        <div class="bar-chart">
                            <div class="chart-y-axis">
                                <span>40</span>
                                <span>30</span>
                                <span>20</span>
                                <span>10</span>
                                <span>0</span>
                            </div>
                            <div class="chart-bars">
                                <div v-for="(month, i) in (props.monthlyReports || [])" :key="i" class="bar-wrapper">
                                    <div class="bar" :style="{ height: chartMax ? (month.value / chartMax * 100) + '%' : '0%' }"></div>
                                    <span class="bar-label">{{ month.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="category-stats-grid">
                <CategoryStatsBlock
                    title="Probleminės kategorijos"
                    :categories="props.problematicCategories"
                />
                <CategoryStatsBlock
                    title="Efektyviausiai sprendžiamos problemos"
                    :categories="props.effectivelyResolved"
                />
            </div>

            <div class="bottom-stats-grid">
                <EmployeeActivityBlock :employees="props.employeeActivity" />
                <InstitutionResultsBlock :institutions="props.institutionResults" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.statistics-page {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 40px;
    max-width: 1400px;
    margin: 0 auto;
    min-height: calc(100vh - 120px);
    overflow-x: clip;
}

@media (max-width: 640px) {
    .statistics-page {
        padding: 16px 12px;
    }
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 30px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.download-header-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: transparent;
    border: 1px solid var(--color-primary, #1946A0);
    border-radius: 5px;
    color: #333333;
    font-size: 14px;
    cursor: pointer;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    transition: all 0.2s;
}

.download-header-btn:hover {
    background-color: #f5f5f5;
}

.download-icon {
    width: 16px;
    height: 16px;
    display: block;
    flex-shrink: 0;
}

.filters-section {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 30px;
    width: 100%;
    min-width: 0;
}

.filters-section .app-select-group {
    width: 100%;
    min-width: 0;
}

.chart-filter {
    width: auto;
    min-width: 150px;
}

.chart-filter .app-select-group {
    margin-bottom: 0;
}

.kpi-chart-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
    min-width: 0;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    min-width: 0;
}

.kpi-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.kpi-title {
    font-size: 14px;
    color: #666;
    margin: 0 0 12px 0;
    font-weight: 400;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.kpi-value {
    font-size: 28px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px 0;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.kpi-mini-chart {
    margin-top: 10px;
    height: 20px;
    display: flex;
    align-items: flex-end;
    color: var(--color-primary, #1946A0);
}

.kpi-mini-chart svg {
    width: 100%;
    height: 100%;
}

.chart-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-width: 0;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    color: #333;
}

.chart-container {
    height: 300px;
    padding: 20px 0;
    max-width: 100%;
}

.bar-chart {
    display: flex;
    height: 100%;
    gap: 10px;
    align-items: flex-end;
    width: 100%;
    min-width: 0;
}

.chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding-right: 10px;
    font-size: 12px;
    color: #666;
    min-width: 30px;
}

.chart-bars {
    display: flex;
    flex: 1;
    gap: 8px;
    align-items: flex-end;
    height: 100%;
}

.bar-wrapper {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}

.bar {
    width: 100%;
    background: var(--color-primary, #1946A0);
    border-radius: 4px 4px 0 0;
    min-height: 2px;
    transition: height 0.3s ease;
}

.bar-label {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
    text-align: center;
    line-height: 1.2;
    max-width: 100%;
    word-break: break-word;
}

.category-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 30px;
    min-width: 0;
}

.bottom-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    min-width: 0;
}

@media (max-width: 1100px) {
    .kpi-chart-row {
        grid-template-columns: 1fr;
    }

    .kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .filters-section {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .category-stats-grid,
    .bottom-stats-grid {
        grid-template-columns: 1fr;
    }

    .chart-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .bar-chart {
        min-width: 440px;
    }
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }

    .page-title {
        font-size: 20px;
    }

    .download-header-btn {
        width: 100%;
        justify-content: center;
        box-sizing: border-box;
    }

    .kpi-grid {
        grid-template-columns: 1fr;
    }

    .kpi-card {
        padding: 16px;
    }

    .kpi-value {
        font-size: 24px;
    }

    .chart-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .chart-title {
        font-size: 16px;
    }

    .chart-filter {
        width: 100%;
        min-width: 0;
    }

    .chart-container {
        height: 260px;
        padding: 12px 0;
    }

    .bar-chart {
        min-width: 400px;
    }

    .bar-label {
        font-size: 10px;
    }
}

</style>
