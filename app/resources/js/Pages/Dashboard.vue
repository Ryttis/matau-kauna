<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue';
import FilterModal from '@/Components/App/Modals/FilterModal.vue';
import ProblemsTable from '@/Components/Problems/ProblemsTable.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash-es'
import { PROBLEM_STATUS_LABELS, PROBLEM_STATUS_OPTIONS } from '@/constants/problemStatus'

const props = defineProps({
    problems: Object,
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

const statusLabels = PROBLEM_STATUS_LABELS
const allStatusOptions = props.statusOptions || PROBLEM_STATUS_OPTIONS

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
    if (selectedProblems.value.length === 1) {
        const problem = problemsList.value.find(p => p.id === selectedProblems.value[0])
        selectedProblem.value = problem || null
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
    if (selectedProblems.value.length === 0) {
        return
    }
    
    if (selectedProblems.value.length === 1) {
        const problemId = selectedProblems.value[0]
        router.visit(route('problems.assign.single', problemId))
    } else {
        const params = new URLSearchParams()
        selectedProblems.value.forEach(id => {
            params.append('problem_ids[]', id)
        })
        router.visit(route('problems.assign') + '?' + params.toString())
    }
}

const handleSelectAll = (checked) => {
    if (checked) {
        selectedProblems.value = problemsList.value.map(p => p.id)
    } else {
        selectedProblems.value = []
    }
}

const handleSelectItem = (problemId) => {
    const index = selectedProblems.value.indexOf(problemId)
    if (index > -1) {
        selectedProblems.value.splice(index, 1)
    } else {
        selectedProblems.value.push(problemId)
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
        <div class="search-wrp">
            <input
                v-model="search"
                @input="handleSearch"
                type="text"
                class="search-input"
                placeholder="Ieškoti"
            />
            <img
                src="/icons/filter-icon.png"
                @click="showFilterModal = true"
                class="filter-icon"
            />
        </div>

        <ProblemsTable
            :problems="problemsList"
            :selected-problems="selectedProblems"
            :status-labels="statusLabels"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
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
                <img src="/icons/right-next-arrow.png" alt="Rodyklė" class="view-more-arrow" />
            </a>
        </div>

        <div class="list-navigation">
            <AppButton 
                text="Priskirti problemą" 
                as-button 
                @click="handleAssignProblems"
            />
            <AppButton
                text="Atvaizduoti/keisti būseną"
                as-button
                @click="openStatusModal()"
            />
            <div class="export-wrapper" ref="exportWrapper">
                <AppButton
                    text="Eksportuoti į .xlsx"
                    variant="secondary"
                    as-button
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
        </div>

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
    </AuthenticatedLayout>
</template>

<style scoped>
.search-wrp {
    display: flex;
    gap: 40px;
    align-items: center;
}

.search-input {
    width: 340px;
    height: 40px;
    border: none;
    border-radius: 20px;
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
    margin: 40px auto;
    width: 400px;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.export-wrapper {
    position: relative;
}

.export-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 5px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
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
    border-radius: 4px;
}

.export-option:hover {
    background-color: #f6f7f9;
}

</style>
