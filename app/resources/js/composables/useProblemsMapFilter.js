import { ref, computed } from 'vue'
import { parseDateFilterInput } from '@/utils/parseDateFilterInput'

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

export function useProblemsMapFilter(problemsSource) {
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
        const query = (searchQuery.value || '').trim().toLowerCase()
        if (query) {
            result = result.filter(
                (problem) =>
                    (problem.description || '').toLowerCase().includes(query) ||
                    (problem.description_preview || '').toLowerCase().includes(query) ||
                    (problem.internal_number || '').toLowerCase().includes(query) ||
                    (problem.address || '').toLowerCase().includes(query)
            )
        }
        if ((filterState.value.statuses || []).length > 0) {
            result = result.filter((problem) => filterState.value.statuses.includes(problem.status))
        }
        if (filterState.value.problemType && filterState.value.problemType !== 'all') {
            result = result.filter((problem) => problem.category === filterState.value.problemType)
        }
        const from = parseDateFilterInput(filterState.value.dateFrom)
        const to = parseDateFilterInput(filterState.value.dateTo)
        if (from || to) {
            result = result.filter((problem) => isCreatedAtInRange(problem.created_at, from, to))
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
