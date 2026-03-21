import { ref, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'

export const REPORTER_STATS_PERIOD_OPTIONS = [
    { value: 'all', label: 'Laikotarpis: Visi' },
    { value: '6', label: 'Laikotarpis: Paskutiniai 6 mėn.' },
    { value: '12', label: 'Laikotarpis: Paskutiniai 12 mėn.' },
    { value: 'year', label: 'Laikotarpis: Šie metai' },
]

export function getDateRangeForPeriod(period) {
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

export function buildReporterStatisticsParams(period, category) {
    const { date_from, date_to } = getDateRangeForPeriod(period)
    return {
        period: period === 'all' ? undefined : period,
        category: category || undefined,
        date_from: date_from || undefined,
        date_to: date_to || undefined,
    }
}

export function useReporterStatisticsFilters(getFilters) {
    const periodFilter = ref('all')
    const categoryFilter = ref('')
    const syncingFromProps = ref(false)

    function applyFilters() {
        router.get(
            route('reporter.statistics'),
            buildReporterStatisticsParams(periodFilter.value, categoryFilter.value),
            { preserveState: false }
        )
    }

    watch(
        () => getFilters(),
        (filters) => {
            if (!filters) return
            syncingFromProps.value = true
            periodFilter.value = filters.period ?? 'all'
            categoryFilter.value = filters.category ?? ''
            nextTick(() => {
                syncingFromProps.value = false
            })
        },
        { immediate: true, deep: true }
    )

    watch([periodFilter, categoryFilter], () => {
        if (syncingFromProps.value) return
        applyFilters()
    })

    return {
        periodFilter,
        categoryFilter,
        applyFilters,
    }
}
