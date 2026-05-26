import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useProblemsMapFilter } from '@/composables/useProblemsMapFilter'
import { buildReporterProblemDisplayRows } from '@/utils/reporterProblemRows'
import { getReporterStatusKey } from '@/constants/domain/problemStatus'

function watchRootId(problem) {
    return problem?.merged_into_problem_id ?? problem?.id
}

export function useDashboardReporter(props) {
    const showOnlyMyReports = ref(props.onlyMine ?? false)
    const showOnlyWatched = ref(props.onlyWatched ?? false)
    watch(() => props.onlyMine, (onlyMine) => {
        showOnlyMyReports.value = !!onlyMine
    })
    watch(() => props.onlyWatched, (onlyWatched) => {
        showOnlyWatched.value = !!onlyWatched
    })

    const reporterAllProblems = computed(() => {
        if (!props.reporterView) return []
        const raw = props.problems
        if (Array.isArray(raw)) return raw
        if (raw && typeof raw === 'object' && !raw.data && !raw.current_page) {
            return Object.values(raw)
        }
        return []
    })

    const reporterProblems = computed(() => {
        const all = reporterAllProblems.value
        if (!showOnlyMyReports.value) {
            return all
        }
        return all.filter((problem) => !!problem?.reported_by_me)
    })

    const watchOverridesByRoot = ref({})
    const reporterWatchLoadingRootId = ref(null)

    watch(
        () => props.problems,
        () => {
            watchOverridesByRoot.value = {}
        },
    )

    function isReporterProblemWatched(problem) {
        if (!problem) return false
        const root = watchRootId(problem)
        const override = watchOverridesByRoot.value[root]
        if (override !== undefined) return !!override
        return !!problem.is_watched
    }

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
        hasActiveFilters: reporterHasActiveFilters,
    } = useProblemsMapFilter(reporterProblems, {
        restrictToCurrentYear: true,
        reporterOnlyMine: showOnlyMyReports,
        reporterOnlyWatched: showOnlyWatched,
        isWatched: isReporterProblemWatched,
        statusForFilter: (problem) => problem?.reporter_status || getReporterStatusKey(problem?.status),
    })

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
            (row) => row.isGroupMain && row.groupIds?.length && row.groupIds.every((id) => ids.has(id))
        )
        return fullGroups.length === 1 ? fullGroups[0].problem.id : null
    })

    const reporterSelectedProblems = computed(() => {
        const ids = reporterSelectedIds.value
        if (!ids.size) return []
        return reporterFilteredProblems.value.filter((p) => ids.has(p.id))
    })

    async function applyWatchToggle(problem) {
        if (!problem || reporterWatchLoadingRootId.value != null) return
        const root = watchRootId(problem)
        reporterWatchLoadingRootId.value = root
        try {
            const { data } = await axios.post(route('problems.watch.toggle', problem.id))
            watchOverridesByRoot.value = {
                ...watchOverridesByRoot.value,
                [root]: !!data.watched,
            }
        } finally {
            reporterWatchLoadingRootId.value = null
        }
    }

    async function reporterHandleStebeti() {
        if (reporterWatchLoadingRootId.value != null) return

        const roots = reporterSelectionRoots.value
        if (!roots.length) return

        const allWatched = roots.every((p) => isReporterProblemWatched(p))
        const toToggle = allWatched
            ? roots
            : roots.filter((p) => !isReporterProblemWatched(p))

        if (!toToggle.length) return

        for (const p of toToggle) {
            await applyWatchToggle(p)
        }

        reporterSelectedIds.value = new Set()
    }

    function reporterToggleWatchFromList(problem) {
        applyWatchToggle(problem)
    }

    const reporterSelectionRoots = computed(() => {
        const selected = reporterSelectedProblems.value
        if (!selected.length) return []
        const roots = new Map()
        selected.forEach((p) => {
            const root = watchRootId(p)
            if (!roots.has(root)) roots.set(root, p)
        })
        return [...roots.values()]
    })

    const reporterSelectedIsWatched = computed(() => {
        const roots = reporterSelectionRoots.value
        if (!roots.length) return false
        return roots.every((p) => isReporterProblemWatched(p))
    })

    const reporterCanToggleWatchSelection = computed(() => {
        if (reporterWatchLoadingRootId.value != null) return false
        return reporterSelectionRoots.value.length > 0
    })

    const reporterWatchBusy = computed(() => reporterWatchLoadingRootId.value != null)

    const reporterStebetiButtonLoading = computed(() => {
        if (reporterWatchLoadingRootId.value == null || !reporterSelectedProblemId.value) {
            return false
        }
        const p = reporterFilteredProblems.value.find((x) => x.id === reporterSelectedProblemId.value)
        if (!p) return false
        return watchRootId(p) === reporterWatchLoadingRootId.value
    })

    function syncReporterFiltersToServer() {
        const params = {}
        if (showOnlyMyReports.value) {
            params.only_mine = 1
        }
        if (showOnlyWatched.value) {
            params.only_watched = 1
        }
        router.get(route('dashboard'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }

    function reporterToggleOnlyMine(event) {
        showOnlyMyReports.value = !!event?.target?.checked
        reporterSelectedIds.value = new Set()
        syncReporterFiltersToServer()
    }

    function reporterToggleOnlyMineFromFilter(onlyMine) {
        showOnlyMyReports.value = !!onlyMine
        reporterSelectedIds.value = new Set()
        syncReporterFiltersToServer()
    }

    function reporterToggleOnlyWatched(event) {
        showOnlyWatched.value = !!event?.target?.checked
        reporterSelectedIds.value = new Set()
        syncReporterFiltersToServer()
    }

    function reporterToggleOnlyWatchedFromFilter(onlyWatched) {
        showOnlyWatched.value = !!onlyWatched
        reporterSelectedIds.value = new Set()
        syncReporterFiltersToServer()
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
        reporterHandleStebeti,
        reporterToggleWatchFromList,
        isReporterProblemWatched,
        reporterSelectedIsWatched,
        reporterCanToggleWatchSelection,
        reporterWatchLoadingRootId,
        reporterWatchBusy,
        reporterStebetiButtonLoading,
        showOnlyMyReports,
        showOnlyWatched,
        reporterToggleOnlyMine,
        reporterToggleOnlyMineFromFilter,
        reporterToggleOnlyWatched,
        reporterToggleOnlyWatchedFromFilter,
        reporterProblems,
        reporterHasActiveFilters,
    }
}
