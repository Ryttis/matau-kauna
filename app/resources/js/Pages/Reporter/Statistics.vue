<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
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

const { periodFilter, categoryFilter } = useReporterStatisticsFilters(() => props.filters)

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
        gap: 12px;
    }

    .reporter-statistics-filter-select {
        width: 100%;
        min-width: 0;
    }
}
</style>
