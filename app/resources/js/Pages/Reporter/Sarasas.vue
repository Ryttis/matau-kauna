<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import TableViewLink from '@/Components/Reporter/TableViewLink.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import SearchFilterBar from '@/Components/App/SearchFilterBar.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'
import { buildReporterProblemDisplayRows } from '@/utils/reporterProblemRows'

const props = defineProps({
    problems: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const showFilterModal = ref(false)
const searchQuery = ref(props.filters?.search ?? '')
const reportNumberFilter = ref(props.filters?.report_number ?? '')
const categoryFilter = ref(props.filters?.category ?? '')
const dateFromFilter = ref(props.filters?.date_from ?? '')
const dateToFilter = ref(props.filters?.date_to ?? '')

watch(() => props.filters, (f) => {
    if (!f) return
    searchQuery.value = f.search ?? ''
    reportNumberFilter.value = f.report_number ?? ''
    categoryFilter.value = f.category ?? ''
    dateFromFilter.value = f.date_from ?? ''
    dateToFilter.value = f.date_to ?? ''
}, { immediate: true })

const applyFilters = (params = {}) => {
    router.get(route('reporter.sarasas'), {
        search: searchQuery.value || undefined,
        report_number: (params.reportNumber ?? reportNumberFilter.value) || undefined,
        category: (params.problemType ?? categoryFilter.value) || undefined,
        date_from: (params.dateFrom ?? dateFromFilter.value) || undefined,
        date_to: (params.dateTo ?? dateToFilter.value) || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['problems', 'filters'],
    })
}

const debouncedSearch = debounce(() => applyFilters(), 300)

function openFilterModal() {
    showFilterModal.value = true
}

function handleFilterClose() {
    showFilterModal.value = false
}

function handleFilterApply(payload) {
    showFilterModal.value = false
    reportNumberFilter.value = payload.reportNumber ?? ''
    categoryFilter.value = payload.problemType ?? ''
    dateFromFilter.value = payload.dateFrom ?? ''
    dateToFilter.value = payload.dateTo ?? ''
    applyFilters({
        reportNumber: payload.reportNumber,
        problemType: payload.problemType,
        dateFrom: payload.dateFrom,
        dateTo: payload.dateTo,
    })
}

function handleFilterClear() {
    showFilterModal.value = false
    reportNumberFilter.value = ''
    categoryFilter.value = ''
    dateFromFilter.value = ''
    dateToFilter.value = ''
    router.get(route('reporter.sarasas'), { search: searchQuery.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['problems', 'filters'],
    })
}

const filtersActive = computed(() => {
    if ((searchQuery.value || '').trim() !== '') {
        return true
    }
    if ((reportNumberFilter.value || '').trim() !== '') {
        return true
    }
    if ((categoryFilter.value || '').trim() !== '') {
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

const flatProblems = computed(() => props.problems || [])
const displayRows = computed(() => buildReporterProblemDisplayRows(flatProblems.value))

const truncate = (str, maxLen = 28) => {
    if (!str) return ''
    const s = String(str).trim()
    return s.length <= maxLen ? s : s.slice(0, maxLen) + '…'
}

function rowProblem(row) {
    return row.problem ?? row
}

const columns = [
    { key: 'internal_number', label: 'Nr.', sortable: false, value: (row) => rowProblem(row).internal_number },
    {
        key: 'status',
        label: 'Būsena',
        sortable: false,
        value: (row) => PROBLEM_STATUS_LABELS[rowProblem(row).status] || rowProblem(row).status,
    },
    {
        key: 'created_at',
        label: 'Data',
        sortable: false,
        value: (row) => (rowProblem(row).created_at ? new Date(rowProblem(row).created_at).toLocaleDateString('lt-LT') : ''),
    },
    {
        key: 'category',
        label: 'Kategorija',
        sortable: false,
        value: (row) => truncate(rowProblem(row).category, 18),
    },
    {
        key: 'address',
        label: 'Adresas',
        sortable: false,
        value: (row) => truncate(rowProblem(row).address, 28),
    },
    {
        key: 'is_published_publicly',
        label: 'Viešinimas',
        sortable: false,
        value: (row) => (rowProblem(row).is_published_publicly ? 'Paskelbta' : 'Nepaskelbta'),
    },
    {
        key: 'view',
        label: 'Veiksmai',
        sortable: false,
        cellComponent: TableViewLink,
        cellProps: (row) => ({ problemId: rowProblem(row).id }),
    },
]

function getRowKey(row) {
    return row.problem ? row.problem.id : row.id
}

function sarasasRowClass(row) {
    if (!row.problem) return ''
    const classes = []
    if (!row.isGroupMain) classes.push('reporter-sarasas-merged-row')
    const inGroup = row.groupIds && row.groupIds.length > 1
    if (inGroup && !row.isLastInGroup) classes.push('reporter-sarasas-group-inner')
    if (inGroup && row.isLastInGroup) classes.push('reporter-sarasas-group-end')
    return classes.join(' ')
}
</script>

<template>
    <Head title="Sąrašas" />

    <AuthenticatedLayout>
        <div class="reporter-sarasas-page admin-tab">
            <h1 class="reporter-sarasas-title">Sąrašas</h1>
            <p class="reporter-sarasas-desc">Mano iškeltos problemos</p>
            <div class="reporter-sarasas-toolbar">
                <SearchFilterBar
                    v-model="searchQuery"
                    placeholder="Ieškoti"
                    :filters-active="filtersActive"
                    @input="debouncedSearch"
                    @filter-click="openFilterModal"
                />
            </div>
            <div class="reporter-sarasas-table-wrap table">
                <EntityTable
                    :items="displayRows"
                    :columns="columns"
                    :row-key="getRowKey"
                    :row-class="sarasasRowClass"
                />
            </div>
            <p v-if="flatProblems.length === 0" class="reporter-sarasas-empty">Nėra iškeltų problemų</p>
        </div>

        <FilterModal
            :show="showFilterModal"
            variant="dashboard"
            :filters="{
                reportNumber: reportNumberFilter,
                problemType: categoryFilter,
                dateFrom: dateFromFilter,
                dateTo: dateToFilter,
            }"
            :categories="categories"
            :all-status-options="[]"
            @close="handleFilterClose"
            @apply="handleFilterApply"
            @clear="handleFilterClear"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.reporter-sarasas-page {
    max-width: 100%;
}

.reporter-sarasas-title {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.reporter-sarasas-desc {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #666;
}

.reporter-sarasas-toolbar {
    margin-bottom: 24px;
    max-width: 400px;
}

.reporter-sarasas-table-wrap {
    margin-top: 0;
}

.reporter-sarasas-table-wrap :deep(table) {
    margin-top: 0;
}

.reporter-sarasas-empty {
    margin-top: 24px;
    font-size: 14px;
    color: #666;
}

.reporter-sarasas-table-wrap :deep(.reporter-sarasas-merged-row) {
    background-color: #f5f7fa;
}

.reporter-sarasas-table-wrap :deep(.reporter-sarasas-merged-row td:first-of-type) {
    padding-left: 1.25rem;
}

.reporter-sarasas-table-wrap :deep(.reporter-sarasas-group-inner td) {
    border-bottom: none;
}

.reporter-sarasas-table-wrap :deep(.reporter-sarasas-group-end td) {
    border-bottom: 2px solid #e0e0e0;
}
</style>
