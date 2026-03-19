import { ref, computed } from 'vue'

const DEFAULT_FILTER_STATE = {
    reportNumber: '',
    statuses: [],
    problemType: 'all',
    dateFrom: '',
    dateTo: '',
}

export function parseDateFilter(str) {
    if (!str || !str.trim()) return null
    const s = str.trim()
    const ddmmyy = s.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/)
    if (ddmmyy) return new Date(+ddmmyy[3], +ddmmyy[2] - 1, +ddmmyy[1])
    const iso = new Date(s)
    return isNaN(iso.getTime()) ? null : iso
}

function isInDateRange(createdAt, dateFrom, dateTo) {
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
        const set = new Set()
        arr.forEach((p) => { if (p?.category) set.add(p.category) })
        return [...set].sort()
    })

    const filteredProblems = computed(() => {
        const list = Array.isArray(problemsSource.value) ? problemsSource.value : []
        let result = list
        const reportNum = (filterState.value.reportNumber || '').trim().toLowerCase()
        if (reportNum) {
            result = result.filter((p) =>
                (p.internal_number || '').toLowerCase().includes(reportNum)
            )
        }
        const q = (searchQuery.value || '').trim().toLowerCase()
        if (q) {
            result = result.filter(
                (p) =>
                    (p.description || '').toLowerCase().includes(q) ||
                    (p.description_preview || '').toLowerCase().includes(q) ||
                    (p.internal_number || '').toLowerCase().includes(q) ||
                    (p.address || '').toLowerCase().includes(q)
            )
        }
        if ((filterState.value.statuses || []).length > 0) {
            result = result.filter((p) => filterState.value.statuses.includes(p.status))
        }
        if (filterState.value.problemType && filterState.value.problemType !== 'all') {
            result = result.filter((p) => p.category === filterState.value.problemType)
        }
        const from = parseDateFilter(filterState.value.dateFrom)
        const to = parseDateFilter(filterState.value.dateTo)
        if (from || to) {
            result = result.filter((p) => isInDateRange(p.created_at, from, to))
        }
        return result
    })

    const problemsToShowOnMap = computed(() => {
        const list = filteredProblems.value
        if (selectedIds.value.size === 0) return list
        return list.filter((p) => selectedIds.value.has(p.id))
    })

    function toggle(id) {
        const next = new Set(selectedIds.value)
        if (next.has(id)) next.delete(id)
        else next.add(id)
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
