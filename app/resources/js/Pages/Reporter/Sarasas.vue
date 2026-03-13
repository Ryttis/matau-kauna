<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import TableViewLink from '@/Components/Reporter/TableViewLink.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import { ICON_FILTER } from '@/constants/icons'
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
const reportNumberFilter = ref(props.filters?.report_number ?? '')
const categoryFilter = ref(props.filters?.category ?? '')
const dateFromFilter = ref(props.filters?.date_from ?? '')
const dateToFilter = ref(props.filters?.date_to ?? '')

watch(() => props.filters, (f) => {
    if (!f) return
    reportNumberFilter.value = f.report_number ?? ''
    categoryFilter.value = f.category ?? ''
    dateFromFilter.value = f.date_from ?? ''
    dateToFilter.value = f.date_to ?? ''
}, { immediate: true })

function openFilterModal() {
    showFilterModal.value = true
}

function handleFilterClose() {
    showFilterModal.value = false
}

function handleFilterApply(payload) {
    showFilterModal.value = false
    router.get(route('reporter.sarasas'), {
        report_number: payload.reportNumber || undefined,
        category: payload.problemType || undefined,
        date_from: payload.dateFrom || undefined,
        date_to: payload.dateTo || undefined,
    }, { preserveState: false })
}

function handleFilterClear() {
    showFilterModal.value = false
    router.get(route('reporter.sarasas'))
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
        key: 'title',
        label: 'Pavadinimas',
        sortable: false,
        value: (row) => truncate(row.title, 32),
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
            <div class="reporter-sarasas-header">
                <div>
                    <h1 class="reporter-sarasas-title">Sąrašas</h1>
                    <p class="reporter-sarasas-desc">Mano iškeltos problemos</p>
                </div>
                <button
                    type="button"
                    class="reporter-sarasas-filter-btn"
                    aria-label="Filtruoti"
                    @click="openFilterModal"
                >
                    <img :src="ICON_FILTER" class="reporter-sarasas-filter-icon" alt="" />
                    <span>Filtruoti</span>
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

.reporter-sarasas-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}

.reporter-sarasas-title {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.reporter-sarasas-desc {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.reporter-sarasas-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border: 1px solid var(--color-primary, #1946A0);
    border-radius: 15px;
    background: transparent;
    color: var(--color-primary, #1946A0);
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    flex-shrink: 0;
}

.reporter-sarasas-filter-btn:hover {
    background: rgba(25, 70, 160, 0.08);
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
