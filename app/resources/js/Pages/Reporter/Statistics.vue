<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import ReporterStatisticsProblemTable from '@/Components/Reporter/ReporterStatisticsProblemTable.vue'
import ReporterStatisticsKpiCards from '@/Components/Reporter/ReporterStatisticsKpiCards.vue'
import ReporterStatisticsMonthlyChart from '@/Components/Reporter/ReporterStatisticsMonthlyChart.vue'
import ReporterStatisticsCategoryBreakdown from '@/Components/Reporter/ReporterStatisticsCategoryBreakdown.vue'
import { REPORTER_STATS_PERIOD_OPTIONS } from '@/constants/domain/reporterStatistics'
import { useReporterStatisticsFilters } from '@/composables/useReporterStatisticsFilters'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    total: { type: Number, default: 0 },
    solvedCount: { type: Number, default: 0 },
    byCategory: { type: Array, default: () => [] },
    monthlyReports: { type: Array, default: () => [] },
    nagrinejamosProblemos: { type: Array, default: () => [] },
    issprestosProblemos: { type: Array, default: () => [] },
})

const { periodFilter, categoryFilter, dateFromFilter, dateToFilter } = useReporterStatisticsFilters(() => props.filters)

const categoryOptions = computed(() => {
    const options = [{ value: '', label: 'Kategorija: Visos' }]
    ;(props.categories || []).forEach((categoryName) => {
        options.push({ value: categoryName, label: `Kategorija: ${categoryName}` })
    })
    return options
})
</script>

<template>
    <Head title="Statistika" />

    <AuthenticatedLayout>
        <div class="reporter-statistics-page admin-tab">
            <h1 class="reporter-statistics-title">Statistika</h1>
            <p class="reporter-statistics-desc">
                Statistinė informacija apie Jūsų nagrinėjamas ir nagrinėtas problemas
            </p>

            <div class="reporter-statistics-filters">
                <div class="reporter-statistics-filters-row">
                    <AppSelect
                        v-model="periodFilter"
                        class="reporter-statistics-filter-select"
                        variant="white"
                        :rounded="true"
                    >
                        <option v-for="opt in REPORTER_STATS_PERIOD_OPTIONS" :key="opt.value" :value="opt.value">
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
                <div class="reporter-statistics-filters-row">
                    <AppDatePicker
                        v-model="dateFromFilter"
                        label="Data nuo"
                        placeholder="Pasirinkite datą"
                        class="reporter-statistics-filter-date"
                    />
                    <AppDatePicker
                        v-model="dateToFilter"
                        label="Data iki"
                        placeholder="Pasirinkite datą"
                        class="reporter-statistics-filter-date"
                        :min-date="dateFromFilter || undefined"
                    />
                </div>
            </div>

            <ReporterStatisticsKpiCards :total="total" :solved-count="solvedCount" />

            <div class="reporter-statistics-grid">
                <ReporterStatisticsMonthlyChart :monthly-reports="monthlyReports" />
                <ReporterStatisticsCategoryBreakdown :by-category="byCategory" />
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
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 24px;
}

.reporter-statistics-filters-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    width: 100%;
}

.reporter-statistics-filter-select {
    min-width: 220px;
}

.reporter-statistics-filter-date {
    min-width: 200px;
}

.reporter-statistics-filter-select :deep(.app-select-group) {
    margin-bottom: 0;
}

.reporter-statistics-filter-date :deep(.app-date-picker) {
    margin-bottom: 0;
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

.reporter-statistics-tables-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-top: 24px;
    align-items: start;
}

@media (max-width: 900px) {
    .reporter-statistics-tables-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .reporter-statistics-filters {
        gap: 10px;
    }

    .reporter-statistics-filters-row {
        gap: 12px;
    }

    .reporter-statistics-filter-select,
    .reporter-statistics-filter-date {
        width: 100%;
        min-width: 0;
    }
}
</style>
