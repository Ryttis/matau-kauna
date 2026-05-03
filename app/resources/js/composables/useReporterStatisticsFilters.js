import { ref, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    REPORTER_STATS_PERIOD_OPTIONS,
    getDateRangeForPeriod,
    buildReporterStatisticsParams,
    findPresetMatchingDateRange,
} from '@/constants/domain/reporterStatistics'

export { REPORTER_STATS_PERIOD_OPTIONS, getDateRangeForPeriod, buildReporterStatisticsParams, findPresetMatchingDateRange }

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
            const serverPeriodRaw = filters.period != null && filters.period !== '' ? String(filters.period) : 'all'

            dateFromFilter.value = serverFrom
            dateToFilter.value = serverTo

            if (serverFrom || serverTo) {
                const byDates = findPresetMatchingDateRange(serverFrom, serverTo)
                if (['6', '12', 'year'].includes(serverPeriodRaw) && byDates === serverPeriodRaw) {
                    periodFilter.value = serverPeriodRaw
                } else if (['6', '12', 'year'].includes(serverPeriodRaw) && byDates) {
                    periodFilter.value = byDates
                } else if (byDates) {
                    periodFilter.value = byDates
                } else {
                    periodFilter.value = 'custom'
                }
            } else {
                periodFilter.value = ['6', '12', 'year', 'custom'].includes(serverPeriodRaw)
                    ? serverPeriodRaw
                    : 'all'
            }

            categoryFilter.value = filters.category ?? ''
            nextTick(() => {
                syncingFromProps.value = false
            })
        },
        { immediate: true, deep: true }
    )

    watch(periodFilter, (newVal) => {
        if (syncingFromProps.value) return
        if (newVal !== 'custom') {
            dateFromFilter.value = ''
            dateToFilter.value = ''
        }
        scheduleApply()
    })

    watch(categoryFilter, () => {
        if (syncingFromProps.value) return
        scheduleApply()
    })

    watch([dateFromFilter, dateToFilter], () => {
        if (syncingFromProps.value) return
        const from = dateFromFilter.value
        const to = dateToFilter.value
        if (!from && !to) {
            if (periodFilter.value === 'custom') {
                syncingFromProps.value = true
                periodFilter.value = 'all'
                nextTick(() => {
                    syncingFromProps.value = false
                })
            }
            scheduleApply()
            return
        }
        const matching = findPresetMatchingDateRange(from, to)
        syncingFromProps.value = true
        periodFilter.value = matching || 'custom'
        nextTick(() => {
            syncingFromProps.value = false
            scheduleApply()
        })
    })

    return {
        periodFilter,
        categoryFilter,
        dateFromFilter,
        dateToFilter,
        applyFilters,
    }
}
