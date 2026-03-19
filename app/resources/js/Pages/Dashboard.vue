<script setup>
import AppButton from '@/Components/App/AppButton.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import ProblemsTable from '@/Components/Problems/ProblemsTable.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemsMapList from '@/Components/Map/ProblemsMapList.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useProblemsMapFilter } from '@/composables/useProblemsMapFilter'
import { useProblemDisplayRows } from '@/composables/useProblemDisplayRows'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { debounce } from 'lodash-es'
import { PROBLEM_STATUS_LABELS, PROBLEM_STATUS_OPTIONS } from '@/constants/problemStatus'
import { ICON_FILTER, ICON_SEARCH, ICON_CIRCLE_ARROW_RIGHT } from '@/constants/icons'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
    reporterView: {
        type: Boolean,
        default: false,
    },
    onlyMine: {
        type: Boolean,
        default: false,
    },
    problems: [Object, Array],
    filters: Object,
    sortBy: String,
    sortDirection: String,
    categories: Array,
    statusOptions: Array,
})

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
const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()

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

function buildReporterDisplayRows(flatList) {
    const list = flatList || []
    const idSet = new Set(list.map((p) => p.id))
    const mains = list.filter((p) => {
        const parentId = p.merged_into_problem_id
        if (!parentId) return true
        return !idSet.has(parentId)
    })
    const rows = []
    mains.forEach((main) => {
        const merged = list.filter((p) => p.merged_into_problem_id === main.id)
        const groupIds = [main.id, ...merged.map((m) => m.id)]
        rows.push({
            problem: main,
            isGroupMain: true,
            isLastInGroup: merged.length === 0,
            groupIds,
        })
        merged.forEach((m, idx) => {
            rows.push({
                problem: m,
                isGroupMain: false,
                isLastInGroup: idx === merged.length - 1,
                groupIds,
            })
        })
    })
    return rows
}

const reporterDisplayRows = computed(() => buildReporterDisplayRows(reporterFilteredProblems.value))

function reporterToggleGroup(groupIds) {
    const next = new Set(reporterSelectedIds.value)
    const allSelected = groupIds.length > 0 && groupIds.every((id) => next.has(id))
    if (allSelected) groupIds.forEach((id) => next.delete(id))
    else groupIds.forEach((id) => next.add(id))
    reporterSelectedIds.value = next
}

const reporterShowFilterModal = ref(false)
function reporterOpenFilterModal() { reporterShowFilterModal.value = true }
function reporterHandleFilterApply(filters) {
    reporterApplyFilters(filters)
    reporterShowFilterModal.value = false
}
function reporterHandleFilterClear() { reporterClearFilters() }

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
    const fullGroups = rows.filter((r) => r.isGroupMain && r.groupIds?.length && r.groupIds.every((id) => ids.has(id)))
    return fullGroups.length === 1 ? fullGroups[0].problem.id : null
})
function reporterHandleStebeti() {
    if (!reporterSelectedProblemId.value) return
    router.visit(route('problems.reviewProgress', reporterSelectedProblemId.value))
}

const showOnlyMyReports = ref(props.onlyMine ?? false)
watch(() => props.onlyMine, (v) => { showOnlyMyReports.value = !!v })
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

const {
    canSeeProblemAdminButtons,
    canSeeInstitutionAdminButtons,
    canExport,
    canDeleteProblem,
    canPublish,
    isEmployee,
} = useAuth()

const selectedProblemIdForReview = computed(() =>
    selectedProblems.value.length === 1 ? selectedProblems.value[0] : null
)
const allStatusOptions = computed(() => {
    const raw = props.statusOptions || PROBLEM_STATUS_OPTIONS
    return Array.isArray(raw) ? raw : Object.entries(raw || {}).map(([value, label]) => ({ value, label: typeof label === 'string' ? label : value }))
})

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
        }
    })
}

const loadMoreProblems = () => {
    if (!props.problems?.next_page_url) return

    const url = new URL(props.problems.next_page_url, window.location.origin)
    const page = url.searchParams.get('page')

    router.get(route('dashboard'), {
        page: page,
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
        only: ['problems'],
        onSuccess: (page) => {
            if (page.props.problems?.data) {
                const newItems = page.props.problems.data.slice(0, 6)
                const existingIds = new Set(problemsList.value.map(p => p.id))
                const uniqueNewItems = newItems.filter(item => !existingIds.has(item.id))
                problemsList.value = [...problemsList.value, ...uniqueNewItems]
            }
            setTimeout(() => {
                const currentUrl = new URL(window.location.href)
                currentUrl.searchParams.delete('page')
                window.history.replaceState({}, '', currentUrl.toString())
            }, 0)
        }
    })
}

watch(() => props.problems, (newProblems, oldProblems) => {
    if (!newProblems?.data || !Array.isArray(newProblems.data)) {
        problemsList.value = []
        return
    }

    const currentPage = newProblems.current_page || 1
    const oldPage = oldProblems?.current_page || 1

    if (!oldProblems || currentPage === 1) {
        problemsList.value = newProblems.data.slice(0, 6)
    } else if (currentPage > oldPage) {
        const newItems = newProblems.data.slice(0, 6)
        const existingIds = new Set(problemsList.value.map(p => p.id))
        const uniqueNewItems = newItems.filter(item => !existingIds.has(item.id))
        problemsList.value = [...problemsList.value, ...uniqueNewItems]
    } else if (currentPage < oldPage) {
        problemsList.value = newProblems.data.slice(0, 6)
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
            status: status
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showStatusModal.value = false
                selectedProblems.value = []
                selectedProblem.value = null
                loadProblems(false)
            }
        })
    } else if (selectedProblem.value) {
        router.patch(route('problems.updateStatus', selectedProblem.value.id), {
            status: status
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showStatusModal.value = false
                selectedProblem.value = null
                loadProblems(false)
            }
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
        selectedProblem.value = row ? row.problem : problemsList.value.find((p) => p.id === firstId) || null
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
        scope: scope,
        page: props.problems.current_page || 1,
    })

    window.location.href = route('problems.export') + '?' + params.toString()
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
        selectedProblems.value.forEach(id => params.append('problem_ids[]', id))
        router.visit(route('problems.assign') + '?' + params.toString())
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

const handleConfirmDelete = () => {
    if (selectedProblems.value.length === 0) return
    router.delete(route('problems.destroy', selectedProblems.value[0]), {
        preserveScroll: false,
        onSuccess: () => {
            showDeleteModal.value = false
            selectedProblems.value = []
            loadProblems(false)
        },
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
    selectedProblemsAllPublished.value ? 'Nebepaskelbti viešai' : 'Paskelbti viešai'
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

const removePageFromUrl = () => {
    const url = new URL(window.location.href)
    if (url.searchParams.has('page')) {
        url.searchParams.delete('page')
        window.history.replaceState({}, '', url.toString())
    }
}

onMounted(() => {
    removePageFromUrl()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div v-if="reporterView" class="reporter-dashboard">
            <div class="reporter-dashboard-top">
                <div class="reporter-dashboard-toolbar">
                    <div class="reporter-dashboard-search-wrap">
                        <img :src="ICON_SEARCH" class="reporter-dashboard-search-icon" alt="" aria-hidden="true" />
                        <input
                            v-model="reporterSearchQuery"
                            type="text"
                            class="reporter-dashboard-search"
                            placeholder="ieškoti"
                        />
                    </div>
                    <button
                        type="button"
                        class="reporter-dashboard-filter-btn"
                        aria-label="Filtruoti"
                        @click="reporterOpenFilterModal"
                    >
                        <img :src="ICON_FILTER" class="reporter-dashboard-filter-icon" alt="" />
                    </button>
                </div>
                <label class="reporter-dashboard-checkbox-label">
                    <input
                        v-model="showOnlyMyReports"
                        type="checkbox"
                        class="reporter-dashboard-checkbox"
                        @change="reporterToggleOnlyMine"
                    />
                    <span>Rodyti tik mano iškeltas problemas</span>
                </label>
            </div>
            <div class="reporter-dashboard-layout">
                <div class="reporter-dashboard-map-wrap">
                    <KaunasProblemsMap
                        :problems="reporterProblemsToShowOnMap"
                        marker-class="reporter-dashboard-marker"
                        @open-problem="reporterOpenProblemView"
                    />
                </div>
                <aside class="reporter-dashboard-list">
                    <h3 class="reporter-dashboard-list-title">Sąrašas</h3>
                    <ProblemsMapList
                        :display-rows="reporterDisplayRows"
                        :all-count="reporterProblems.length"
                        :selected-ids="reporterSelectedIds"
                        @select="reporterToggle"
                        @select-group="reporterToggleGroup"
                        @open="reporterOpenProblemView"
                    />
                    <div class="reporter-dashboard-actions">
                        <AppButton
                            text="Stebėti"
                            variant="primary"
                            as-button
                            :disabled="!reporterSelectedProblemId"
                            @click="reporterHandleStebeti"
                        />
                    </div>
                </aside>
            </div>
            <FilterModal
                :show="reporterShowFilterModal"
                variant="publicMap"
                :filters="reporterFilterState"
                :categories="reporterCategoryOptions"
                :only-mine="showOnlyMyReports"
                @close="reporterShowFilterModal = false"
                @apply="reporterHandleFilterApply"
                @clear="reporterHandleFilterClear"
                @only-mine-change="reporterToggleOnlyMineFromFilter"
            />
        </div>

        <template v-else>
        <div class="search-wrp">
            <input
                v-model="search"
                @input="handleSearch"
                type="text"
                class="search-input"
                placeholder="Ieškoti"
            />
            <img
                :src="ICON_FILTER"
                @click="showFilterModal = true"
                class="filter-icon"
            />
        </div>

        <ProblemsTable
            :problems="problemsList"
            :display-rows="displayRows"
            :selected-problems="selectedProblems"
            :status-labels="PROBLEM_STATUS_LABELS"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            :can-publish="canPublish"
            @sort="handleSort"
            @select-all="handleSelectAll"
            @select-item="handleSelectItem"
        />

        <div class="table-footer-wrp" v-if="problems.next_page_url && problemsList.length >= 6">
            <a
                :href="problems.next_page_url"
                @click.prevent="loadMoreProblems"
                class="view-more-link"
            >
                <span>Žiūrėti daugiau</span>
                <img :src="ICON_CIRCLE_ARROW_RIGHT" alt="Rodyklė" class="view-more-arrow" />
            </a>
        </div>

        <div class="list-navigation">
            <template v-if="canSeeProblemAdminButtons">
                <AppButton
                    text="Priskirti problemą"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="handleAssignProblems"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="openStatusModal()"
                />
                <div v-if="canExport" class="export-wrapper" ref="exportWrapper">
                    <AppButton
                        text="Eksportuoti į .xlsx"
                        variant="secondary"
                        as-button
                        class="list-nav-btn"
                        @click="showExportDropdown = !showExportDropdown"
                    />
                    <div v-if="showExportDropdown" class="export-dropdown" ref="exportDropdown">
                        <button @click="handleExport('all')" class="export-option">
                            Eksportuoti visus rezultatus
                        </button>
                        <button @click="handleExport('current_page')" class="export-option">
                            Eksportuoti tik esamą puslapį
                        </button>
                    </div>
                </div>
                <AppButton
                    v-if="canPublish"
                    :text="bulkPublishButtonLabel"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="handleBulkPublishToggle"
                />
                <AppButton
                    v-if="canDeleteProblem"
                    text="Šalinti"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length !== 1"
                    @click="handleDeleteProblems"
                />
            </template>

            <template v-else-if="canSeeInstitutionAdminButtons">
                <AppButton
                    text="Priskirti/Peradresuoti problemą"
                    variant="primary"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length !== 1"
                    @click="handleAssignInstitution"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="openStatusModal()"
                />
                <AppButton
                    text="Stebėti"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="!selectedProblemIdForReview"
                    @click="handleStebeti"
                />
            </template>

            <template v-else-if="isEmployee">
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="openStatusModal()"
                />
                <AppButton
                    text="Stebėti"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="!selectedProblemIdForReview"
                    @click="handleStebeti"
                />
            </template>

            <template v-else>
                <AppButton
                    text="Stebėti"
                    as-button
                    class="list-nav-btn"
                    :disabled="!selectedProblemIdForReview"
                    @click="handleStebeti"
                />
            </template>
        </div>

        <ConfirmModal
            :open="showDeleteModal"
            message="Ar tikrai norite ištrinti šią problemą?"
            confirm-text="Patvirtinti"
            cancel-text="Atšaukti"
            footer-layout="column"
            confirm-variant="primary"
            width="369px"
            max-height="275px"
            @close="showDeleteModal = false"
            @confirm="handleConfirmDelete"
        />

        <StatusChangeModal
            :show="showStatusModal"
            :problem="selectedProblem"
            :selected-problem-ids="selectedProblems"
            @close="handleModalClose"
            @save="handleStatusChange"
        />

        <FilterModal
            :show="showFilterModal"
            :filters="{
                reportNumber: reportNumberFilter,
                problemType: categoryFilter,
                dateFrom: dateFromFilter,
                dateTo: dateToFilter,
                status: statusFilter
            }"
            :categories="categories"
            :all-status-options="allStatusOptions"
            @close="handleFilterClose"
            @apply="handleFilterApply"
            @clear="handleFilterClear"
        />
        </template>
    </AuthenticatedLayout>
</template>

<style scoped>
.reporter-dashboard {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 0;
    flex: 1;
    height: 100%;
    min-height: 500px;
}

.reporter-dashboard-top {
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex-shrink: 0;
}

.reporter-dashboard-toolbar {
    display: flex;
    align-items: center;
    gap: 16px;
}

.reporter-dashboard-search-wrap {
    position: relative;
    flex: 1;
    min-width: 0;
    max-width: 400px;
    display: flex;
    align-items: center;
}

.reporter-dashboard-search-icon {
    position: absolute;
    left: 14px;
    width: 20px;
    height: 20px;
    pointer-events: none;
    object-fit: contain;
}

.reporter-dashboard-search {
    width: 100%;
    height: 40px;
    border: none;
    border-radius: 15px;
    background: #f6f7f9;
    color: #333;
    padding: 0 16px 0 44px;
    font-size: 14px;
    box-sizing: border-box;
}

.reporter-dashboard-search::placeholder {
    color: #999;
}

.reporter-dashboard-filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: none;
    border-radius: 15px;
    background: transparent;
    cursor: pointer;
}

.reporter-dashboard-filter-btn:hover {
    background: #f0f0f0;
}

.reporter-dashboard-filter-icon {
    width: 24px;
    height: 24px;
}

.reporter-dashboard-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    margin: 0;
    padding: 4px 0;
}

.reporter-dashboard-checkbox-label span {
    user-select: none;
}

.reporter-dashboard-checkbox {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    cursor: pointer;
}

.reporter-dashboard-layout {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr);
    gap: 1rem;
    flex: 1;
    min-height: 450px;
    height: 100%;
}

.reporter-dashboard-map-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #fafafa;
}

.reporter-dashboard-map-wrap :deep(.kaunas-map-container) {
    min-height: 350px;
    height: 350px;
}

.reporter-dashboard-map-wrap :deep(.leaflet-marker-icon.reporter-dashboard-marker) {
    background: transparent !important;
    border: none !important;
}

.reporter-dashboard-list {
    background: #f6f7f9;
    padding: 1.25rem 1rem 1.5rem;
    overflow-y: auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.reporter-dashboard-list-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    flex-shrink: 0;
}

.reporter-dashboard-actions {
    flex-shrink: 0;
    display: flex;
    justify-content: center;
}

.reporter-dashboard-actions .app-button {
    width: 100%;
    max-width: 200px;
    height: 40px;
    border-radius: 15px;
    padding: 0 20px;
}

@media (max-width: 900px) {
    .reporter-dashboard-layout {
        grid-template-columns: 1fr;
    }
}

.search-wrp {
    display: flex;
    gap: 40px;
    align-items: center;
}

.search-input {
    width: 340px;
    height: 40px;
    border: none;
    border-radius: 15px;
    background-color: #F6F7F9;
    color: #333;
    padding: 0 20px;
}

.filter-icon {
    display: block;
    margin: auto 0;
    cursor: pointer;
}


.table-footer-wrp {
    margin-top: 20px;
    display: flex;
    gap: 20px;
    font-size: 16px;
    align-items: center;
}

.view-more-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: #333;
    font-size: 16px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.view-more-link:hover {
    opacity: 0.8;
}

.view-more-arrow {
    display: block;
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.list-navigation {
    margin: 40px 0;
    width: 100%;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    align-items: center;
    position: relative;
    gap: var(--button-grid-gap, 16px);
    overflow: visible;
}

.list-navigation > * {
    margin-bottom: 0 !important;
}

.list-navigation .list-nav-btn,
.list-navigation .export-wrapper .app-button {
    width: var(--button-width);
    max-width: 100%;
    height: var(--button-height);
    min-height: var(--button-height);
    box-sizing: border-box;
    border-radius: var(--button-border-radius);
    padding: 9px 24px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Open Sans', sans-serif;
    font-weight: 400;
    font-size: 16px;
    line-height: 150%;
    letter-spacing: -0.011em;
    text-align: center;
}

.list-navigation :deep(.app-button--primary),
.list-navigation .export-wrapper :deep(.app-button--primary) {
    background-color: var(--color-primary) !important;
    color: var(--color-white) !important;
}

.list-navigation :deep(.app-button--primary:hover),
.list-navigation :deep(.app-button--primary:focus-visible),
.list-navigation .export-wrapper :deep(.app-button--primary:hover),
.list-navigation .export-wrapper :deep(.app-button--primary:focus-visible) {
    background-color: var(--color-primary-hover) !important;
    color: var(--color-white) !important;
}

.list-navigation :deep(.app-button--secondary),
.list-navigation .export-wrapper :deep(.app-button--secondary) {
    background-color: var(--color-white) !important;
    color: var(--color-text) !important;
    border: 1px solid var(--color-primary) !important;
}

.list-navigation :deep(.app-button--secondary:hover),
.list-navigation :deep(.app-button--secondary:focus-visible),
.list-navigation .export-wrapper :deep(.app-button--secondary:hover),
.list-navigation .export-wrapper :deep(.app-button--secondary:focus-visible) {
    background-color: #f5f5f5 !important;
    color: var(--color-text) !important;
    border: 1px solid var(--color-primary) !important;
}

.list-navigation :deep(.app-button--disabled),
.list-navigation :deep(.app-button--disabled:hover),
.list-navigation .export-wrapper :deep(.app-button--disabled),
.list-navigation .export-wrapper :deep(.app-button--disabled:hover) {
    opacity: 0.5 !important;
}

.export-wrapper {
    position: relative;
}

.export-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    margin-top: 5px;
    background: var(--color-white);
    border: 1px solid var(--color-border);
    border-radius: 15px;
    padding: 5px;
    min-width: 200px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.export-option {
    width: 100%;
    padding: 10px;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    border-radius: 15px;
}

.export-option:hover {
    background-color: #f6f7f9;
}

</style>
