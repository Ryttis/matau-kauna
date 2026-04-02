import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import { useProblemDisplayRows } from '@/composables/useProblemDisplayRows'
import { PROBLEM_STATUS, PROBLEM_STATUS_OPTIONS } from '@/constants/domain/problemStatus'

export function useDashboardProblemList(props) {
    const search = ref(props.filters?.search || '')
    const statusFilter = ref(props.filters?.status || '')
    const categoryFilter = ref(props.filters?.category || '')
    const reportNumberFilter = ref(props.filters?.reportNumber || '')
    const dateFromFilter = ref(props.filters?.dateFrom || '')
    const dateToFilter = ref(props.filters?.dateTo || '')
    const sortBy = ref(props.sortBy || 'created_at')
    const sortDirection = ref(props.sortDirection || 'desc')
    const selectedProblems = ref([])
    const showStatusModal = ref(false)
    const selectedProblem = ref(null)
    const showFilterModal = ref(false)
    const showExportDropdown = ref(false)
    const exportWrapper = ref(null)
    const exportDropdown = ref(null)
    const problemsList = ref([])
    const showDeleteModal = ref(false)

    const { displayRows } = useProblemDisplayRows(problemsList)

    function allProblemsFlatFromList(list) {
        const out = []
        for (const main of list) {
            out.push(main)
            for (const mergedProblem of main.merged_problems || main.mergedProblems || []) {
                out.push(mergedProblem)
            }
        }
        return out
    }

    const statusByProblemId = computed(() => {
        const map = new Map()
        for (const p of allProblemsFlatFromList(problemsList.value)) {
            map.set(p.id, p.status)
        }
        return map
    })

    const selectedIncludesResolvedProblem = computed(() => {
        for (const id of selectedProblems.value) {
            if (statusByProblemId.value.get(id) === PROBLEM_STATUS.RESOLVED) {
                return true
            }
        }
        return false
    })

    const selectedProblemIdForReview = computed(() =>
        selectedProblems.value.length === 1 ? selectedProblems.value[0] : null
    )

    const allStatusOptions = computed(() => {
        const raw = props.statusOptions || PROBLEM_STATUS_OPTIONS
        return Array.isArray(raw) ? raw : Object.entries(raw || {}).map(([value, label]) => ({
            value,
            label: typeof label === 'string' ? label : value,
        }))
    })

    const removePageFromUrl = () => {
        const url = new URL(window.location.href)
        if (url.searchParams.has('page')) {
            url.searchParams.delete('page')
            window.history.replaceState({}, '', url.toString())
        }
    }

    const loadProblems = (resetPage = true) => {
        const params = {
            search: search.value || null,
            status: statusFilter.value || null,
            category: categoryFilter.value || null,
            report_number: reportNumberFilter.value || null,
            date_from: dateFromFilter.value || null,
            date_to: dateToFilter.value || null,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        }
        if (resetPage) {
            params.page = 1
        }
        router.get(route('dashboard'), params, {
            preserveState: !resetPage,
            preserveScroll: !resetPage,
            replace: resetPage,
            onFinish: () => {
                removePageFromUrl()
            },
        })
    }

    const loadMoreProblems = () => {
        if (!props.problems?.next_page_url) return
        const url = new URL(props.problems.next_page_url, window.location.origin)
        const page = url.searchParams.get('page')
        router.get(route('dashboard'), {
            page,
            search: search.value || null,
            status: statusFilter.value || null,
            category: categoryFilter.value || null,
            report_number: reportNumberFilter.value || null,
            date_from: dateFromFilter.value || null,
            date_to: dateToFilter.value || null,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['problems', 'flash'],
            onSuccess: (inertiaPage) => {
                if (inertiaPage.props.problems?.data) {
                    const newItems = inertiaPage.props.problems.data.slice(0, 6)
                    const existingIds = new Set(problemsList.value.map((p) => p.id))
                    const uniqueNewItems = newItems.filter((item) => !existingIds.has(item.id))
                    problemsList.value = [...problemsList.value, ...uniqueNewItems]
                }
                setTimeout(() => {
                    const currentUrl = new URL(window.location.href)
                    currentUrl.searchParams.delete('page')
                    window.history.replaceState({}, '', currentUrl.toString())
                }, 0)
            },
        })
    }

    watch(() => props.problems, (newProblems, oldProblems) => {
        if (!newProblems) {
            problemsList.value = []
            return
        }
        const rows = Array.isArray(newProblems)
            ? newProblems
            : Array.isArray(newProblems.data)
                ? newProblems.data
                : []
        if (rows.length === 0) {
            problemsList.value = []
            return
        }
        const isPaginator = !Array.isArray(newProblems) && newProblems.data
        const currentPage = isPaginator ? (newProblems.current_page || 1) : 1
        const oldPage = oldProblems && !Array.isArray(oldProblems) && oldProblems.current_page
            ? oldProblems.current_page
            : 1
        if (!isPaginator || !oldProblems || currentPage === 1) {
            problemsList.value = rows.slice(0, 6)
        } else if (currentPage > oldPage) {
            const newItems = rows.slice(0, 6)
            const existingIds = new Set(problemsList.value.map((p) => p.id))
            const uniqueNewItems = newItems.filter((item) => !existingIds.has(item.id))
            problemsList.value = [...problemsList.value, ...uniqueNewItems]
        } else if (currentPage < oldPage) {
            problemsList.value = rows.slice(0, 6)
        }
    }, { immediate: true, deep: true })

    const handleSearch = debounce(() => {
        problemsList.value = []
        loadProblems(true)
    }, 300)

    const handleSort = (field) => {
        if (sortBy.value === field) {
            sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
        } else {
            sortBy.value = field
            sortDirection.value = 'desc'
        }
        problemsList.value = []
        loadProblems(true)
    }

    const handleStatusChange = (status) => {
        if (selectedProblems.value.length > 0) {
            router.post(route('problems.bulkUpdateStatus'), {
                problem_ids: selectedProblems.value,
                status,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    showStatusModal.value = false
                    selectedProblems.value = []
                    selectedProblem.value = null
                    loadProblems(false)
                },
            })
        } else if (selectedProblem.value) {
            router.patch(route('problems.updateStatus', selectedProblem.value.id), {
                status,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    showStatusModal.value = false
                    selectedProblem.value = null
                    loadProblems(false)
                },
            })
        }
    }

    const handleModalClose = () => {
        showStatusModal.value = false
        selectedProblem.value = null
    }

    const openStatusModal = () => {
        if (selectedProblems.value.length >= 1) {
            const firstId = selectedProblems.value[0]
            const row = displayRows.value.find((r) => r.problem.id === firstId)
            selectedProblem.value = row
                ? row.problem
                : problemsList.value.find((p) => p.id === firstId) || null
        } else {
            selectedProblem.value = null
        }
        showStatusModal.value = true
    }

    const handleExport = (scope) => {
        const params = new URLSearchParams({
            search: search.value || '',
            status: statusFilter.value || '',
            category: categoryFilter.value || '',
            report_number: reportNumberFilter.value || '',
            date_from: dateFromFilter.value || '',
            date_to: dateToFilter.value || '',
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
            scope,
            page: props.problems?.current_page || 1,
        })
        window.location.href = `${route('problems.export')}?${params.toString()}`
        showExportDropdown.value = false
    }

    watch([statusFilter, categoryFilter, reportNumberFilter, dateFromFilter, dateToFilter], () => {
        problemsList.value = []
        loadProblems(true)
    })

    const handleFilterApply = (filters) => {
        statusFilter.value = filters.status || ''
        categoryFilter.value = filters.problemType || ''
        reportNumberFilter.value = filters.reportNumber || ''
        dateFromFilter.value = filters.dateFrom || ''
        dateToFilter.value = filters.dateTo || ''
        showFilterModal.value = false
        problemsList.value = []
        loadProblems(true)
    }

    const handleFilterClear = () => {
        statusFilter.value = ''
        categoryFilter.value = ''
        reportNumberFilter.value = ''
        dateFromFilter.value = ''
        dateToFilter.value = ''
        problemsList.value = []
        loadProblems(true)
    }

    const handleFilterClose = () => {
        showFilterModal.value = false
    }

    const handleAssignProblems = () => {
        if (selectedProblems.value.length === 0) return
        if (selectedProblems.value.length === 1) {
            const problemId = selectedProblems.value[0]
            router.visit(route('problems.assign.single', problemId))
        } else {
            const params = new URLSearchParams()
            selectedProblems.value.forEach((id) => params.append('problem_ids[]', id))
            router.visit(`${route('problems.assign')}?${params.toString()}`)
        }
    }

    const handleAssignInstitution = () => {
        if (selectedProblems.value.length !== 1) return
        router.visit(route('problems.assignInstitution', selectedProblems.value[0]))
    }

    const handleDeleteProblems = () => {
        if (selectedProblems.value.length === 0) return
        showDeleteModal.value = true
    }

    const deleteConfirmMessage = computed(() => {
        const n = selectedProblems.value.length
        if (n <= 1) {
            return 'Ar tikrai norite ištrinti šią problemą?'
        }
        return `Ar tikrai norite pašalinti pažymėtas problemas (${n})?`
    })

    const handleConfirmDelete = () => {
        if (selectedProblems.value.length === 0) return
        const ids = [...selectedProblems.value]
        const onSuccess = () => {
            showDeleteModal.value = false
            selectedProblems.value = []
            loadProblems(false)
        }
        if (ids.length === 1) {
            router.delete(route('problems.destroy', ids[0]), {
                preserveScroll: false,
                onSuccess,
            })
            return
        }
        router.post(route('problems.bulkDestroy'), { problem_ids: ids }, {
            preserveScroll: false,
            onSuccess,
        })
    }

    const handleStebeti = () => {
        if (!selectedProblemIdForReview.value) return
        router.visit(route('problems.reviewProgress', selectedProblemIdForReview.value))
    }

    const selectedProblemsAllPublished = computed(() => {
        if (selectedProblems.value.length === 0) return false
        const rows = displayRows.value
        const ids = selectedProblems.value
        for (const id of ids) {
            const row = rows.find((r) => r.problem?.id === id)
            if (!row?.problem?.is_published_publicly) return false
        }
        return true
    })

    const bulkPublishButtonLabel = computed(() =>
        (selectedProblemsAllPublished.value ? 'Nebepaskelbti viešai' : 'Paskelbti viešai')
    )

    const handleBulkPublishToggle = () => {
        if (selectedProblems.value.length === 0) return
        router.post(route('problems.bulkUpdatePublish'), {
            problem_ids: selectedProblems.value,
            is_published_publicly: !selectedProblemsAllPublished.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedProblems.value = []
                loadProblems(false)
            },
        })
    }

    const handleSelectAll = (checked) => {
        if (checked) {
            selectedProblems.value = displayRows.value.map((r) => r.problem.id)
        } else {
            selectedProblems.value = []
        }
    }

    const handleSelectItem = (payload) => {
        if (Array.isArray(payload)) {
            const allSelected = payload.every((id) => selectedProblems.value.includes(id))
            if (allSelected) {
                selectedProblems.value = selectedProblems.value.filter((id) => !payload.includes(id))
            } else {
                const next = new Set(selectedProblems.value)
                payload.forEach((id) => next.add(id))
                selectedProblems.value = [...next]
            }
        } else {
            const index = selectedProblems.value.indexOf(payload)
            if (index > -1) {
                selectedProblems.value.splice(index, 1)
            } else {
                selectedProblems.value.push(payload)
            }
        }
    }

    const handleClickOutside = (event) => {
        if (exportWrapper.value && !exportWrapper.value.contains(event.target)) {
            showExportDropdown.value = false
        }
    }

    onMounted(() => {
        removePageFromUrl()
        document.addEventListener('click', handleClickOutside)
    })

    onUnmounted(() => {
        document.removeEventListener('click', handleClickOutside)
    })

    return {
        search,
        statusFilter,
        categoryFilter,
        reportNumberFilter,
        dateFromFilter,
        dateToFilter,
        sortBy,
        sortDirection,
        selectedProblems,
        showStatusModal,
        selectedProblem,
        showFilterModal,
        showExportDropdown,
        exportWrapper,
        exportDropdown,
        problemsList,
        showDeleteModal,
        deleteConfirmMessage,
        displayRows,
        selectedIncludesResolvedProblem,
        selectedProblemIdForReview,
        allStatusOptions,
        bulkPublishButtonLabel,
        loadMoreProblems,
        handleSearch,
        handleSort,
        handleStatusChange,
        handleModalClose,
        openStatusModal,
        handleExport,
        handleFilterApply,
        handleFilterClear,
        handleFilterClose,
        handleAssignProblems,
        handleAssignInstitution,
        handleDeleteProblems,
        handleConfirmDelete,
        handleStebeti,
        handleBulkPublishToggle,
        handleSelectAll,
        handleSelectItem,
    }
}
