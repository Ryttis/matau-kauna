import { ref, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    REPORTER_STATS_PERIOD_OPTIONS,
    getDateRangeForPeriod,
    buildReporterStatisticsParams,
} from '@/constants/domain/reporterStatistics'

export { REPORTER_STATS_PERIOD_OPTIONS, getDateRangeForPeriod, buildReporterStatisticsParams }

export function useReporterStatisticsFilters(getFilters) {
    const periodFilter = ref('all')
    const categoryFilter = ref('')
    const dateFromFilter = ref('')
    const dateToFilter = ref('')
    const syncingFromProps = ref(false)
    let applyScheduled = false

    function applyFilters() {
        router.get(
            route('reporter.statistics'),
            buildReporterStatisticsParams(
                periodFilter.value,
                categoryFilter.value,
                dateFromFilter.value,
                dateToFilter.value
            ),
            { preserveState: false }
        )
    }

    function scheduleApply() {
        if (syncingFromProps.value) return
        if (applyScheduled) return
        applyScheduled = true
        nextTick(() => {
            applyScheduled = false
            if (!syncingFromProps.value) {
                applyFilters()
            }
        })
    }

    watch(
        () => getFilters(),
        (filters) => {
            if (!filters) return
            syncingFromProps.value = true
            const serverFrom = filters.date_from ? String(filters.date_from).slice(0, 10) : ''
            const serverTo = filters.date_to ? String(filters.date_to).slice(0, 10) : ''
            const hasServerDates = !!(serverFrom || serverTo)
            if (hasServerDates) {
                periodFilter.value = 'all'
                dateFromFilter.value = serverFrom
                dateToFilter.value = serverTo
            } else {
                periodFilter.value = filters.period ?? 'all'
                dateFromFilter.value = ''
                dateToFilter.value = ''
            }
            categoryFilter.value = filters.category ?? ''
            nextTick(() => {
                syncingFromProps.value = false
            })
        },
        { immediate: true, deep: true }
    )

    watch(periodFilter, () => {
        if (syncingFromProps.value) return
        dateFromFilter.value = ''
        dateToFilter.value = ''
        scheduleApply()
    })

    watch(categoryFilter, () => {
        if (syncingFromProps.value) return
        scheduleApply()
    })

    watch([dateFromFilter, dateToFilter], () => {
        if (syncingFromProps.value) return
        const hasDates = !!(dateFromFilter.value || dateToFilter.value)
        if (hasDates && periodFilter.value !== 'all') {
            syncingFromProps.value = true
            periodFilter.value = 'all'
            nextTick(() => {
                syncingFromProps.value = false
                scheduleApply()
            })
            return
        }
        scheduleApply()
    })

    return {
        periodFilter,
        categoryFilter,
        dateFromFilter,
        dateToFilter,
        applyFilters,
    }
}
