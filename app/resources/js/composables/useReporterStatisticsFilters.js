import { ref, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import { buildReporterStatisticsParams } from '@/constants/reporterStatistics'

export {
    REPORTER_STATS_PERIOD_OPTIONS,
    getDateRangeForPeriod,
    buildReporterStatisticsParams,
} from '@/constants/reporterStatistics'

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
