<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import ReporterStatisticsProblemTable from '@/Components/Reporter/ReporterStatisticsProblemTable.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/problemStatus'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    total: { type: Number, default: 0 },
    publishedCount: { type: Number, default: 0 },
    byStatus: { type: Object, default: () => ({ nauja: 0, nagrinejama: 0, isspresta: 0 }) },
    byCategory: { type: Array, default: () => [] },
    monthlyReports: { type: Array, default: () => [] },
    nagrinejamosProblemos: { type: Array, default: () => [] },
    issprestosProblemos: { type: Array, default: () => [] },
})

const periodFilter = ref(props.filters?.period ?? 'all')
const categoryFilter = ref(props.filters?.category ?? '')

const periodOptions = [
    { value: 'all', label: 'Laikotarpis: Visi' },
    { value: '6', label: 'Laikotarpis: Paskutiniai 6 mėn.' },
    { value: '12', label: 'Laikotarpis: Paskutiniai 12 mėn.' },
    { value: 'year', label: 'Laikotarpis: Šie metai' },
]

const categoryOptions = computed(() => {
    const options = [{ value: '', label: 'Kategorija: Visos' }]
    ;(props.categories || []).forEach((categoryName) => {
        options.push({ value: categoryName, label: `Kategorija: ${categoryName}` })
    })
    return options
})

watch(() => props.filters, (filters) => {
    if (!filters) return
    periodFilter.value = filters.period ?? 'all'
    categoryFilter.value = filters.category ?? ''
}, { immediate: true })

function getDateRangeForPeriod(period) {
    if (!period || period === 'all') return { date_from: null, date_to: null }
    const now = new Date()
    const dateTo = now.toISOString().slice(0, 10)
    let dateFrom
    if (period === 'year') {
        dateFrom = `${now.getFullYear()}-01-01`
    } else {
        const monthsBack = parseInt(period, 10) || 12
        const startDate = new Date(now.getFullYear(), now.getMonth() - monthsBack, now.getDate())
        dateFrom = startDate.toISOString().slice(0, 10)
    }
    return { date_from: dateFrom, date_to: dateTo }
}

function applyFilters() {
    const { date_from, date_to } = getDateRangeForPeriod(periodFilter.value)
    router.get(route('reporter.statistics'), {
        period: periodFilter.value === 'all' ? undefined : periodFilter.value,
        category: categoryFilter.value || undefined,
        date_from: date_from || undefined,
        date_to: date_to || undefined,
    }, { preserveState: false })
}

watch([periodFilter, categoryFilter], () => {
    applyFilters()
})

const chartMax = computed(() => {
    const values = (props.monthlyReports || []).map((report) => report.value)
    return Math.max(1, ...values)
})

function monthLabel(monthString) {
    if (!monthString) return ''
    const [year, month] = monthString.split('-').map(Number)
    const firstDayOfMonth = new Date(year, (month || 1) - 1, 1)
    return firstDayOfMonth.toLocaleDateString('lt-LT', { month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head title="Statistika" />

    <AuthenticatedLayout>
        <div class="reporter-statistics-page admin-tab">
            <h1 class="reporter-statistics-title">Statistika</h1>
            <p class="reporter-statistics-desc">Statistinė informacija apie Jūsų nagrinėjamas ir nagrinėtas problemas</p>

            <div class="reporter-statistics-filters">
                <AppSelect
                    v-model="periodFilter"
                    class="reporter-statistics-filter-select"
                    variant="white"
                    :rounded="true"
                >
                    <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </AppSelect>
                <AppSelect
                    v-model="categoryFilter"
                    class="reporter-statistics-filter-select"
                    variant="white"
                    :rounded="true"
                >
                    <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </AppSelect>
            </div>

            <div class="reporter-statistics-kpi">
                <div class="reporter-statistics-card">
                    <h3 class="reporter-statistics-card-title">Iš viso problemų</h3>
                    <p class="reporter-statistics-card-value">{{ total }}</p>
                </div>
                <div class="reporter-statistics-card">
                    <h3 class="reporter-statistics-card-title">Viešai paskelbtos problemos</h3>
                    <p class="reporter-statistics-card-value">{{ publishedCount ?? 0 }}</p>
                </div>
                <div class="reporter-statistics-card">
                    <h3 class="reporter-statistics-card-title">{{ PROBLEM_STATUS_LABELS.nauja || 'Nauja' }}</h3>
                    <p class="reporter-statistics-card-value">{{ byStatus.nauja ?? 0 }}</p>
                </div>
                <div class="reporter-statistics-card">
                    <h3 class="reporter-statistics-card-title">{{ PROBLEM_STATUS_LABELS.nagrinejama || 'Nagrinėjama' }}</h3>
                    <p class="reporter-statistics-card-value">{{ byStatus.nagrinejama ?? 0 }}</p>
                </div>
                <div class="reporter-statistics-card">
                    <h3 class="reporter-statistics-card-title">{{ PROBLEM_STATUS_LABELS.isspresta || 'Išspręsta' }}</h3>
                    <p class="reporter-statistics-card-value">{{ byStatus.isspresta ?? 0 }}</p>
                </div>
            </div>

            <div class="reporter-statistics-grid">
                <div class="reporter-statistics-chart-card">
                    <h3 class="reporter-statistics-chart-title">Pranešimų skaičius per mėnesius</h3>
                    <div class="reporter-statistics-bars">
                        <div v-for="(monthReport, monthIndex) in (monthlyReports || [])" :key="monthIndex" class="reporter-statistics-bar-wrap">
                            <span class="reporter-statistics-bar-value">{{ monthReport.value }}</span>
                            <div class="reporter-statistics-bar-outer">
                                <div
                                    class="reporter-statistics-bar"
                                    :style="{ height: chartMax ? (monthReport.value / chartMax * 100) + '%' : '0%' }"
                                />
                            </div>
                            <span class="reporter-statistics-bar-label">{{ monthLabel(monthReport.month) }}</span>
                        </div>
                    </div>
                </div>

                <div class="reporter-statistics-categories-card">
                    <h3 class="reporter-statistics-categories-title">Pagal kategoriją</h3>
                    <ul v-if="byCategory.length" class="reporter-statistics-categories-list">
                        <li
                            v-for="(categoryRow, categoryIndex) in byCategory"
                            :key="categoryIndex"
                            class="reporter-statistics-categories-item"
                        >
                            <span class="reporter-statistics-cat-name">{{ categoryRow.category }}</span>
                            <span class="reporter-statistics-cat-count">{{ categoryRow.count }}</span>
                        </li>
                    </ul>
                    <p v-else class="reporter-statistics-empty">Nėra duomenų</p>
                </div>
            </div>

            <div class="reporter-statistics-tables-grid">
                <ReporterStatisticsProblemTable
                    title="Nagrinėjamos problemos"
                    :problems="nagrinejamosProblemos"
                    empty-message="Nėra nagrinėjamų problemų"
                />
                <ReporterStatisticsProblemTable
                    title="Išspręstos problemos"
                    :problems="issprestosProblemos"
                    empty-message="Nėra išspręstų problemų"
                />
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.reporter-statistics-page {
    max-width: 100%;
    padding-bottom: 24px;
}

.reporter-statistics-title {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.reporter-statistics-desc {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #666;
}

.reporter-statistics-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.reporter-statistics-filter-select {
    min-width: 220px;
}

.reporter-statistics-filter-select :deep(.app-select-group) {
    margin-bottom: 0;
}

.reporter-statistics-kpi {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 24px;
    align-items: end;
}

@media (max-width: 900px) {
    .reporter-statistics-kpi {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }
}

.reporter-statistics-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reporter-statistics-card-title {
    font-size: 14px;
    color: #666;
    margin: 0 0 8px 0;
    font-weight: 400;
    line-height: 1.3;
    min-height: 2.6em;
}

.reporter-statistics-card-value {
    font-size: 28px;
    font-weight: 600;
    color: #333;
    margin: 0;
    line-height: 1.2;
}

.reporter-statistics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .reporter-statistics-grid {
        grid-template-columns: 1fr;
    }
}

.reporter-statistics-chart-card,
.reporter-statistics-categories-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reporter-statistics-chart-title,
.reporter-statistics-categories-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px 0;
}

.reporter-statistics-bars {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    min-height: 120px;
}

.reporter-statistics-bar-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 0;
}

.reporter-statistics-bar-value {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.reporter-statistics-bar-outer {
    width: 100%;
    max-width: 32px;
    height: 80px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.reporter-statistics-bar {
    width: 100%;
    min-height: 4px;
    background: #1946A0;
    border-radius: 4px 4px 0 0;
}

.reporter-statistics-bar-label {
    font-size: 11px;
    color: #666;
    margin-top: 6px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.reporter-statistics-categories-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.reporter-statistics-categories-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.reporter-statistics-categories-item:last-child {
    border-bottom: none;
}

.reporter-statistics-cat-name {
    color: #333;
}

.reporter-statistics-cat-count {
    font-weight: 600;
    color: #333;
}

.reporter-statistics-empty {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.reporter-statistics-tables-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-top: 24px;
}

@media (max-width: 900px) {
    .reporter-statistics-tables-grid {
        grid-template-columns: 1fr;
    }
}
</style>
