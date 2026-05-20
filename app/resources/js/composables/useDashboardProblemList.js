import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import { useProblemDisplayRows } from '@/composables/useProblemDisplayRows'
import { PROBLEM_STATUS, PROBLEM_STATUS_OPTIONS } from '@/constants/domain/problemStatus'
import { buildInactiveReviewPostBody } from '@/constants/domain/reviewProgressEntry'
import {
    allProblemsFlatFromList,
    buildDashboardListRouterParams,
    computeProblemsListAfterPropsChange,
} from '@/utils/dashboardProblemListHelpers'

const DEFAULT_PAGE_SIZE_OPTIONS = [10, 20, 50, 100]

export function useDashboardProblemList(props) {
    const search = ref(props.filters?.search || '')
    const statusFilter = ref(props.filters?.status || '')
    const categoryFilter = ref(props.filters?.category || '')
    const reportNumberFilter = ref(props.filters?.report_number || props.filters?.reportNumber || '')
    const dateFromFilter = ref(props.filters?.date_from || props.filters?.dateFrom || '')
    const dateToFilter = ref(props.filters?.date_to || props.filters?.dateTo || '')
    const sortBy = ref(props.sortBy || 'created_at')
    const sortDirection = ref(props.sortDirection || 'desc')
    const perPage = ref(Number(props.problems?.per_page) || DEFAULT_PAGE_SIZE_OPTIONS[0])
    const selectedProblems = ref([])
    const showStatusModal = ref(false)
    const selectedProblem = ref(null)
    const showFilterModal = ref(false)
    const showExportDropdown = ref(false)
    const exportWrapper = ref(null)
    const exportDropdown = ref(null)
    const problemsList = ref([])
    const showDeleteModal = ref(false)
    const showInactiveReasonModal = ref(false)
    const inactiveComment = ref('')
    const inactiveSubmitting = ref(false)
    const inactiveSubmitChannel = ref(null)
    const inactiveTargetProblemIds = ref([])
    const statusChangeSubmitting = ref(false)
    const deleteSubmitting = ref(false)
    const bulkPublishSubmitting = ref(false)

    const { displayRows } = useProblemDisplayRows(problemsList)

    const pageSizeOptions = computed(() => {
        const raw = props.pageSizeOptions
        return Array.isArray(raw) && raw.length ? raw : DEFAULT_PAGE_SIZE_OPTIONS
    })

    const currentPage = computed(() => Number(props.problems?.current_page) || 1)
    const lastPage = computed(() => Number(props.problems?.last_page) || 1)
    const totalProblems = computed(() => Number(props.problems?.total) || problemsList.value.length)

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

    const hasActiveListFilters = computed(() => {
        if ((search.value || '').trim() !== '') {
            return true
        }
        if ((reportNumberFilter.value || '').trim() !== '') {
            return true
        }
        if ((categoryFilter.value || '').trim() !== '') {
            return true
        }
        if ((statusFilter.value || '').trim() !== '') {
            return true
        }
        if ((dateFromFilter.value || '').trim() !== '') {
            return true
        }
        if ((dateToFilter.value || '').trim() !== '') {
            return true
        }
        return false
    })

    const loadProblems = (resetPage = true) => {
        if (resetPage) {
            selectedProblems.value = []
        }
        const params = buildDashboardListRouterParams({
            search: search.value,
            status: statusFilter.value,
            category: categoryFilter.value,
            report_number: reportNumberFilter.value,
            date_from: dateFromFilter.value,
            date_to: dateToFilter.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
            page: resetPage ? 1 : currentPage.value,
            per_page: perPage.value,
        })
        router.get(route('dashboard'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: resetPage,
        })
    }

    const goToPage = (page) => {
        const normalizedPage = Math.min(Math.max(Number(page) || 1, 1), lastPage.value)
        if (normalizedPage === currentPage.value) return
        selectedProblems.value = []
        router.get(route('dashboard'), buildDashboardListRouterParams({
            search: search.value,
            status: statusFilter.value,
            category: categoryFilter.value,
            report_number: reportNumberFilter.value,
            date_from: dateFromFilter.value,
            date_to: dateToFilter.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
            page: normalizedPage,
            per_page: perPage.value,
        }), {
            preserveState: true,
            preserveScroll: true,
        })
    }

    const handlePerPageChange = () => {
        const allowed = pageSizeOptions.value
        if (!allowed.includes(Number(perPage.value))) {
            perPage.value = allowed[0] || DEFAULT_PAGE_SIZE_OPTIONS[0]
        }
        loadProblems(true)
    }

    watch(() => props.problems, (newProblems) => {
        problemsList.value = computeProblemsListAfterPropsChange(newProblems)
        if (newProblems?.per_page) {
            perPage.value = Number(newProblems.per_page)
        }
    }, { immediate: true, deep: true })

    const handleSearch = debounce(() => {
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
        selectedProblems.value = []
        loadProblems(true)
    }

    const handleStatusChange = (status) => {
        if (statusChangeSubmitting.value) return
        if (status === PROBLEM_STATUS.INACTIVE) {
            const ids = selectedProblems.value.length > 0
                ? [...selectedProblems.value]
                : (selectedProblem.value ? [selectedProblem.value.id] : [])
            if (ids.length === 0) return
            showStatusModal.value = false
            inactiveTargetProblemIds.value = ids
            inactiveComment.value = ''
            showInactiveReasonModal.value = true
            return
        }

        const finishStatus = () => {
            statusChangeSubmitting.value = false
        }

        if (selectedProblems.value.length > 0) {
            statusChangeSubmitting.value = true
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
                onFinish: finishStatus,
            })
        } else if (selectedProblem.value) {
            statusChangeSubmitting.value = true
            router.patch(route('problems.updateStatus', selectedProblem.value.id), {
                status,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    showStatusModal.value = false
                    selectedProblem.value = null
                    loadProblems(false)
                },
                onFinish: finishStatus,
            })
        }
    }

    const submitInactiveReason = (visibility) => {
        if (inactiveSubmitting.value) return
        const ids = inactiveTargetProblemIds.value
        if (!ids.length) return
        inactiveSubmitting.value = true
        inactiveSubmitChannel.value = visibility

        const finish = () => {
            inactiveSubmitting.value = false
            inactiveSubmitChannel.value = null
            showInactiveReasonModal.value = false
            inactiveComment.value = ''
            inactiveTargetProblemIds.value = []
            selectedProblems.value = []
            selectedProblem.value = null
            loadProblems(false)
        }

        const postAt = (index) => {
            if (index >= ids.length) {
                finish()
                return
            }
            router.post(
                route('problems.reviewProgress.store', ids[index]),
                buildInactiveReviewPostBody(inactiveComment.value, visibility),
                {
                    preserveScroll: true,
                    onFinish: () => postAt(index + 1),
                },
            )
        }
        postAt(0)
    }

    const closeInactiveReasonModal = () => {
        if (inactiveSubmitting.value) return
        showInactiveReasonModal.value = false
        inactiveTargetProblemIds.value = []
        inactiveComment.value = ''
    }

    const handleModalClose = () => {
        if (statusChangeSubmitting.value) return
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
            per_page: perPage.value,
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
        selectedProblems.value = []
        loadProblems(true)
    }

    const handleFilterClear = () => {
        statusFilter.value = ''
        categoryFilter.value = ''
        reportNumberFilter.value = ''
        dateFromFilter.value = ''
        dateToFilter.value = ''
        problemsList.value = []
        selectedProblems.value = []
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
        if (deleteSubmitting.value || selectedProblems.value.length === 0) return
        deleteSubmitting.value = true
        const ids = [...selectedProblems.value]
        const onSuccess = () => {
            showDeleteModal.value = false
            selectedProblems.value = []
            loadProblems(false)
        }
        const onFinish = () => {
            deleteSubmitting.value = false
        }
        if (ids.length === 1) {
            router.delete(route('problems.destroy', ids[0]), {
                preserveScroll: false,
                onSuccess,
                onFinish,
            })
            return
        }
        router.post(route('problems.bulkDestroy'), { problem_ids: ids }, {
            preserveScroll: false,
            onSuccess,
            onFinish,
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
        if (selectedProblems.value.length === 0 || bulkPublishSubmitting.value) {
            return
        }
        bulkPublishSubmitting.value = true
        router.post(route('problems.bulkUpdatePublish'), {
            problem_ids: selectedProblems.value,
            is_published_publicly: !selectedProblemsAllPublished.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedProblems.value = []
                loadProblems(false)
            },
            onFinish: () => {
                bulkPublishSubmitting.value = false
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
        document.addEventListener('click', handleClickOutside)
    })

    onUnmounted(() => {
        document.removeEventListener('click', handleClickOutside)
        handleSearch.cancel()
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
        perPage,
        pageSizeOptions,
        currentPage,
        lastPage,
        totalProblems,
        selectedProblems,
        showStatusModal,
        selectedProblem,
        showFilterModal,
        showExportDropdown,
        exportWrapper,
        exportDropdown,
        problemsList,
        showDeleteModal,
        showInactiveReasonModal,
        inactiveComment,
        inactiveSubmitting,
        inactiveSubmitChannel,
        statusChangeSubmitting,
        deleteSubmitting,
        bulkPublishSubmitting,
        submitInactiveReason,
        closeInactiveReasonModal,
        deleteConfirmMessage,
        displayRows,
        selectedIncludesResolvedProblem,
        selectedProblemIdForReview,
        allStatusOptions,
        bulkPublishButtonLabel,
        goToPage,
        handlePerPageChange,
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
        hasActiveListFilters,
    }
}
