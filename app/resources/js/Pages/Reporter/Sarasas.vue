<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import TableViewLink from '@/Components/Reporter/TableViewLink.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import { ICON_FILTER, ICON_SEARCH } from '@/constants/icons'
import { PROBLEM_STATUS_LABELS } from '@/constants/problemStatus'

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
    }, { preserveState: false })
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
    router.get(route('reporter.sarasas'), { search: searchQuery.value || undefined }, { preserveState: false })
}

const items = computed(() => props.problems || [])

const truncate = (str, maxLen = 28) => {
    if (!str) return ''
    const s = String(str).trim()
    return s.length <= maxLen ? s : s.slice(0, maxLen) + '…'
}

const columns = [
    { key: 'internal_number', label: 'Nr.', sortable: false, value: (row) => row.internal_number },
    {
        key: 'status',
        label: 'Būsena',
        sortable: false,
        value: (row) => PROBLEM_STATUS_LABELS[row.status] || row.status,
    },
    {
        key: 'created_at',
        label: 'Data',
        sortable: false,
        value: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString('lt-LT') : ''),
    },
    {
        key: 'category',
        label: 'Kategorija',
        sortable: false,
        value: (row) => truncate(row.category, 18),
    },
    {
        key: 'address',
        label: 'Adresas',
        sortable: false,
        value: (row) => truncate(row.address, 28),
    },
    {
        key: 'is_published_publicly',
        label: 'Viešinimas',
        sortable: false,
        value: (row) => row.is_published_publicly ? 'Paskelbta' : 'Nepaskelbta',
    },
    {
        key: 'view',
        label: 'Peržiūrėti',
        sortable: false,
        cellComponent: TableViewLink,
        cellProps: (row) => ({ problemId: row.id }),
    },
]

function getRowKey(row) {
    return row.id
}
</script>

<template>
    <Head title="Sąrašas" />

    <AuthenticatedLayout>
        <div class="reporter-sarasas-page admin-tab">
            <h1 class="reporter-sarasas-title">Sąrašas</h1>
            <p class="reporter-sarasas-desc">Mano iškeltos problemos</p>
            <div class="reporter-sarasas-toolbar">
                <div class="reporter-sarasas-search-wrap">
                    <img :src="ICON_SEARCH" class="reporter-sarasas-search-icon" alt="" aria-hidden="true" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="reporter-sarasas-search"
                        placeholder="ieškoti"
                        @input="debouncedSearch"
                    />
                </div>
                <button
                    type="button"
                    class="reporter-sarasas-filter-btn"
                    aria-label="Filtruoti"
                    @click="openFilterModal"
                >
                    <img :src="ICON_FILTER" class="reporter-sarasas-filter-icon" alt="" />
                </button>
            </div>
            <div class="reporter-sarasas-table-wrap table">
                <EntityTable
                    :items="items"
                    :columns="columns"
                    :row-key="getRowKey"
                />
            </div>
            <p v-if="items.length === 0" class="reporter-sarasas-empty">Nėra iškeltų problemų</p>
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
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.reporter-sarasas-search-wrap {
    position: relative;
    flex: 1;
    min-width: 0;
    max-width: 400px;
    display: flex;
    align-items: center;
}

.reporter-sarasas-search-icon {
    position: absolute;
    left: 14px;
    width: 20px;
    height: 20px;
    pointer-events: none;
    object-fit: contain;
}

.reporter-sarasas-search {
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

.reporter-sarasas-search::placeholder {
    color: #999;
}

.reporter-sarasas-filter-btn {
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
    flex-shrink: 0;
}

.reporter-sarasas-filter-btn:hover {
    background: #f0f0f0;
}

.reporter-sarasas-filter-icon {
    width: 20px;
    height: 20px;
    object-fit: contain;
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
</style>
