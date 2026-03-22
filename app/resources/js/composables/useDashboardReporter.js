import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useProblemsMapFilter } from '@/composables/useProblemsMapFilter'
import { buildReporterProblemDisplayRows } from '@/utils/reporterProblemRows'

export function useDashboardReporter(props) {
    const reporterProblems = computed(() => {
        if (!props.reporterView) return []
        const p = props.problems
        if (Array.isArray(p)) return p
        if (p && typeof p === 'object' && !p.data && !p.current_page) return Object.values(p)
        return []
    })

    const {
        searchQuery: reporterSearchQuery,
        filterState: reporterFilterState,
        selectedIds: reporterSelectedIds,
        categoryOptions: reporterCategoryOptions,
        filteredProblems: reporterFilteredProblems,
        problemsToShowOnMap: reporterProblemsToShowOnMap,
        toggle: reporterToggle,
        applyFilters: reporterApplyFilters,
        clearFilters: reporterClearFilters,
    } = useProblemsMapFilter(reporterProblems)

    const reporterDisplayRows = computed(() => buildReporterProblemDisplayRows(reporterFilteredProblems.value))

    function reporterToggleGroup(groupIds) {
        const next = new Set(reporterSelectedIds.value)
        const allSelected = groupIds.length > 0 && groupIds.every((id) => next.has(id))
        if (allSelected) groupIds.forEach((id) => next.delete(id))
        else groupIds.forEach((id) => next.add(id))
        reporterSelectedIds.value = next
    }

    const reporterShowFilterModal = ref(false)
    function reporterOpenFilterModal() {
        reporterShowFilterModal.value = true
    }
    function reporterHandleFilterApply(filters) {
        reporterApplyFilters(filters)
        reporterShowFilterModal.value = false
    }
    function reporterHandleFilterClear() {
        reporterClearFilters()
    }

    function reporterOpenProblemView(problemId) {
        const base = route('reports.show', problemId)
        const query = showOnlyMyReports.value ? '?from=dashboard&only_mine=1' : '?from=dashboard'
        router.visit(base + query)
    }

    const reporterSelectedProblemId = computed(() => {
        const ids = reporterSelectedIds.value
        if (ids.size === 0) return null
        if (ids.size === 1) return [...ids][0]
        const rows = reporterDisplayRows.value
        const fullGroups = rows.filter(
            (r) => r.isGroupMain && r.groupIds?.length && r.groupIds.every((id) => ids.has(id))
        )
        return fullGroups.length === 1 ? fullGroups[0].problem.id : null
    })

    function reporterHandleStebeti() {
        if (!reporterSelectedProblemId.value) return
        router.visit(route('problems.reviewProgress', reporterSelectedProblemId.value))
    }

    const showOnlyMyReports = ref(props.onlyMine ?? false)
    watch(() => props.onlyMine, (v) => {
        showOnlyMyReports.value = !!v
    })

    function reporterToggleOnlyMine() {
        reporterClearFilters()
        const base = route('dashboard')
        const query = showOnlyMyReports.value ? '?only_mine=1' : ''
        router.visit(base + query, { preserveState: false })
    }

    function reporterToggleOnlyMineFromFilter(onlyMine) {
        reporterShowFilterModal.value = false
        reporterClearFilters()
        const base = route('dashboard')
        const query = onlyMine ? '?only_mine=1' : ''
        router.visit(base + query, { preserveState: false })
    }

    return {
        reporterSearchQuery,
        reporterFilterState,
        reporterSelectedIds,
        reporterCategoryOptions,
        reporterFilteredProblems,
        reporterProblemsToShowOnMap,
        reporterToggle,
        reporterDisplayRows,
        reporterToggleGroup,
        reporterShowFilterModal,
        reporterOpenFilterModal,
        reporterHandleFilterApply,
        reporterHandleFilterClear,
        reporterOpenProblemView,
        reporterSelectedProblemId,
        reporterHandleStebeti,
        showOnlyMyReports,
        reporterToggleOnlyMine,
        reporterToggleOnlyMineFromFilter,
        reporterProblems,
    }
}
