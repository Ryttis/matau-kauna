import { ref, computed } from 'vue'

const DEFAULT_FILTER_STATE = {
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
        const q = (searchQuery.value || '').trim().toLowerCase()
        if (q) {
            result = result.filter(
                (p) =>
                    (p.title || '').toLowerCase().includes(q) ||
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
            result = result.filter((p) => {
                const created = p.created_at ? new Date(p.created_at) : null
                if (!created) return false
                if (from && created < from) return false
                if (to && created > to) return false
                return true
            })
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
