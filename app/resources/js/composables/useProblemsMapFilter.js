import { ref, computed } from 'vue'
import { parseDateFilterInput } from '@/utils/parseDateFilterInput'
import { foldLithuanianForSearch } from '@/utils/foldLithuanianForSearch'
import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'

const DEFAULT_FILTER_STATE = {
    reportNumber: '',
    statuses: [],
    problemType: 'all',
    dateFrom: '',
    dateTo: '',
}

function isCreatedAtInRange(createdAt, dateFrom, dateTo) {
    const created = createdAt ? new Date(createdAt) : null
    if (!created) return false
    if (dateFrom && created < dateFrom) return false
    if (dateTo && created > dateTo) return false
    return true
}

export function useProblemsMapFilter(problemsSource, options = {}) {
    const restrictToCurrentYear = !!options.restrictToCurrentYear
    const reporterOnlyMine = options.reporterOnlyMine
    const searchQuery = ref('')
    const filterState = ref({ ...DEFAULT_FILTER_STATE })
    const selectedIds = ref(new Set())

    const categoryOptions = computed(() => {
        const list = problemsSource.value
        const arr = Array.isArray(list) ? list : []
        const categories = new Set()
        arr.forEach((problem) => {
            if (problem?.category) categories.add(problem.category)
        })
        return [...categories].sort()
    })

    const filteredProblems = computed(() => {
        const list = Array.isArray(problemsSource.value) ? problemsSource.value : []
        let result = list
        const reportNum = (filterState.value.reportNumber || '').trim().toLowerCase()
        if (reportNum) {
            result = result.filter((problem) =>
                (problem.internal_number || '').toLowerCase().includes(reportNum)
            )
        }
        const query = foldLithuanianForSearch((searchQuery.value || '').trim())
        if (query) {
            result = result.filter((problem) => {
                const fields = [
                    problem.description,
                    problem.description_preview,
                    problem.internal_number,
                    problem.address,
                ]
                return fields.some((field) => foldLithuanianForSearch(field || '').includes(query))
            })
        }
        if ((filterState.value.statuses || []).length > 0) {
            const selected = filterState.value.statuses
            result = result.filter((problem) => {
                const s = problem.status
                if (selected.includes(s)) return true
                if (
                    reporterOnlyMine?.value &&
                    selected.includes(PROBLEM_STATUS.NEW) &&
                    s === PROBLEM_STATUS.UNREGISTERED
                ) {
                    return true
                }
                return false
            })
        }
        if (filterState.value.problemType && filterState.value.problemType !== 'all') {
            result = result.filter((problem) => problem.category === filterState.value.problemType)
        }
        const from = parseDateFilterInput(filterState.value.dateFrom)
        const to = parseDateFilterInput(filterState.value.dateTo)
        const currentYear = new Date().getFullYear()
        const yearStart = new Date(currentYear, 0, 1, 0, 0, 0, 0)
        const yearEnd = new Date(currentYear, 11, 31, 23, 59, 59, 999)
        const effectiveFrom = restrictToCurrentYear
            ? (from && from > yearStart ? from : yearStart)
            : from
        const effectiveTo = restrictToCurrentYear
            ? (to && to < yearEnd ? to : yearEnd)
            : to

        if (restrictToCurrentYear) {
            result = result.filter((problem) => isCreatedAtInRange(problem.created_at, yearStart, yearEnd))
        }
        if (effectiveFrom || effectiveTo) {
            result = result.filter((problem) => isCreatedAtInRange(problem.created_at, effectiveFrom, effectiveTo))
        }

        if (reporterOnlyMine?.value) {
            result = result.map((problem) =>
                problem.status === PROBLEM_STATUS.UNREGISTERED
                    ? { ...problem, status: PROBLEM_STATUS.NEW }
                    : problem,
            )
        }

        return result
    })

    const problemsToShowOnMap = computed(() => {
        const list = filteredProblems.value
        if (selectedIds.value.size === 0) return list
        return list.filter((problem) => selectedIds.value.has(problem.id))
    })

    function toggle(problemId) {
        const next = new Set(selectedIds.value)
        if (next.has(problemId)) next.delete(problemId)
        else next.add(problemId)
        selectedIds.value = next
    }

    function toggleGroup(groupIds) {
        if (!Array.isArray(groupIds) || groupIds.length === 0) return
        const next = new Set(selectedIds.value)
        const allSelected = groupIds.every((id) => next.has(id))
        if (allSelected) {
            groupIds.forEach((id) => next.delete(id))
        } else {
            groupIds.forEach((id) => next.add(id))
        }
        selectedIds.value = next
    }

    function applyFilters(filters) {
        filterState.value = { ...filters }
    }

    function clearFilters() {
        filterState.value = { ...DEFAULT_FILTER_STATE }
    }

    return {
        searchQuery,
        filterState,
        selectedIds,
        categoryOptions,
        filteredProblems,
        problemsToShowOnMap,
        toggle,
        toggleGroup,
        applyFilters,
        clearFilters,
    }
}
