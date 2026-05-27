<script setup>
import { computed } from 'vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import ProblemTableUnmergeCell from '@/Components/Problems/ProblemTableUnmergeCell.vue'
import ProblemTablePublishCell from '@/Components/Problems/ProblemTablePublishCell.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { formatDateLt, formatDateTimeLt } from '@/utils/dateFormat'

function normalizeProblemList(problems) {
    if (problems == null) return []
    if (Array.isArray(problems)) return problems
    if (Array.isArray(problems.data)) return problems.data
    return []
}

const props = defineProps({
    problems: {
        type: [Array, Object],
        default: null
    },
    displayRows: {
        type: Array,
        default: null
    },
    selectedProblems: {
        type: Array,
        default: () => []
    },
    sortBy: {
        type: String,
        default: 'created_at'
    },
    sortDirection: {
        type: String,
        default: 'desc'
    },
    canPublish: {
        type: Boolean,
        default: false
    },
    selectByRowClick: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['sort', 'select-all', 'select-item'])

const items = computed(() => {
    const dr = props.displayRows
    if (Array.isArray(dr) && dr.length > 0) return dr
    return normalizeProblemList(props.problems)
})

const useRowProblem = computed(() => Boolean(props.displayRows && props.displayRows.length > 0))

const getRowData = (row) => (useRowProblem.value && row.problem ? row.problem : row)

const truncate = (str, maxLen = 28) => {
    if (!str) return ''
    const s = String(str).trim()
    return s.length <= maxLen ? s : s.slice(0, maxLen) + '…'
}

const reporterName = (row) => {
    const problem = getRowData(row)
    const reporter = problem.reported_by_user || problem.reportedByUser
    if (!reporter) return '-'
    const fullName = [reporter.name, reporter.surname]
        .filter(Boolean)
        .join(' ')
        .trim()
    return truncate(fullName || reporter.email || '-', 24)
}

const formatDateTime = (value) => formatDateTimeLt(value, '-')

const columns = computed(() => {
    const base = [
        {
            key: 'internal_number',
            label: 'Nr.',
            sortable: true,
            headerClass: 'problem-col-nr',
            class: 'problem-col-nr',
            value: (row) => getRowData(row).internal_number
        },
        {
            key: 'status',
            label: 'Būsena',
            sortable: true,
            headerClass: 'problem-col-status',
            class: 'problem-col-status',
            cellComponent: StatusBadge,
            cellProps: (row) => ({
                status: getRowData(row).status,
                size: 'small',
            })
        },
        {
            key: 'created_at',
            label: 'Užregistruota',
            sortable: true,
            headerClass: 'problem-col-date',
            class: 'problem-col-date',
            value: (row) => formatDateLt(getRowData(row).created_at)
        },
        {
            key: 'status_updated_at',
            label: 'Atnaujinta',
            sortable: true,
            headerClass: 'problem-col-status-date',
            class: 'problem-col-status-date',
            value: (row) => formatDateTime(getRowData(row).status_updated_at)
        },
        {
            key: 'reporter',
            label: 'Pareiškėjas',
            sortable: false,
            headerClass: 'problem-col-reporter',
            class: 'problem-col-reporter',
            value: reporterName
        },
        {
            key: 'category',
            label: 'Kategorija',
            sortable: true,
            headerClass: 'problem-col-category',
            class: 'problem-col-category',
            value: (row) => truncate(getRowData(row).category, 18)
        },
        {
            key: 'nature',
            label: 'Pobūdis',
            sortable: true,
            headerClass: 'problem-col-nature',
            class: 'problem-col-nature',
            value: (row) => truncate(getRowData(row).problem_nature?.name, 18)
        },
        {
            key: 'address',
            label: 'Adresas',
            sortable: true,
            headerClass: 'problem-col-address',
            class: 'problem-col-address',
            value: (row) => truncate(getRowData(row).address, 28)
        },
        ...(useRowProblem.value && props.canPublish
            ? [{
                key: 'publish',
                label: 'Viešai',
                sortable: false,
                cellComponent: ProblemTablePublishCell,
                cellProps: (row) => ({
                    problem: getRowData(row),
                    canPublish: props.canPublish,
                    showPublishToggle: row.isGroupMain === true,
                })
            }]
            : []),
        ...(useRowProblem.value
            ? [{
                key: 'actions',
                label: 'Veiksmai',
                sortable: false,
                cellComponent: ProblemTableUnmergeCell,
                cellProps: (row) => ({
                    problemId: getRowData(row).id,
                    isMerged: !row.isGroupMain
                })
            }]
            : [])
    ]
    return base
})

const getRowKey = (row) => (row.problem ? row.problem.id : row.id)

const showRowCheckbox = (row) => {
    if (!useRowProblem.value) return true
    return row.isGroupMain === true
}

const getSelectValue = (row) => {
    if (!useRowProblem.value) return getRowKey(row)
    return row.groupIds || [row.problem.id]
}

function problemTableRowClass(row) {
    const classes = []
    if (!row.isGroupMain) classes.push('problem-table-merged-row')
    const inGroup = row.groupIds && row.groupIds.length > 1
    if (inGroup && !row.isLastInGroup) classes.push('problem-table-group-inner')
    if (row.isLastInGroup) classes.push('problem-table-group-end')
    return classes.join(' ')
}
</script>

<template>
    <EntityTable
        :items="items"
        :columns="columns"
        :row-key="getRowKey"
        :sort-by="sortBy"
        :sort-direction="sortDirection"
        selectable
        :selected-keys="selectedProblems"
        :show-checkbox="useRowProblem ? showRowCheckbox : true"
        :get-select-value="useRowProblem ? getSelectValue : null"
        :row-class="useRowProblem ? (row) => problemTableRowClass(row) : null"
        :hide-row-checkboxes="selectByRowClick"
        row-clickable
        @sort="emit('sort', $event)"
        @select-all="emit('select-all', $event)"
        @select-item="emit('select-item', $event)"
    />
</template>

<style scoped>
:deep(.problem-table-merged-row:not(.row-selected)) {
    background-color: #f5f7fa;
}

:deep(.problem-table-merged-row td:first-of-type) {
    padding-left: 1.25rem;
}

:deep(.problem-table-group-inner td) {
    border-bottom: none;
}

:deep(.problem-table-group-end td) {
    border-bottom: 2px solid #e0e0e0;
}

:deep(.problem-col-nr) {
    white-space: nowrap;
    min-width: 7.25rem;
    max-width: 9rem;
}

:deep(.problem-col-status) {
    white-space: nowrap;
    min-width: 8.5rem;
}

:deep(.problem-col-status-date) {
    white-space: nowrap;
    min-width: 8.25rem;
}

:deep(.problem-col-date) {
    white-space: nowrap;
    min-width: 5.5rem;
}

:deep(.problem-col-reporter) {
    min-width: 8rem;
    max-width: 11rem;
    word-break: break-word;
}

:deep(.problem-col-category) {
    min-width: 7rem;
    max-width: 10rem;
}

:deep(.problem-col-address) {
    min-width: 8rem;
    max-width: 14rem;
    word-break: break-word;
}
</style>
