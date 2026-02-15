<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import ProblemTableUnmergeCell from '@/Components/Problems/ProblemTableUnmergeCell.vue'
import ProblemTablePublishCell from '@/Components/Problems/ProblemTablePublishCell.vue'

const props = defineProps({
    problems: {
        type: Array,
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
    statusLabels: {
        type: Object,
        required: true
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
    }
})

const emit = defineEmits(['sort', 'select-all', 'select-item'])

const items = computed(() => {
    if (props.displayRows && props.displayRows.length > 0) return props.displayRows
    return props.problems || []
})

const useRowProblem = computed(() => Boolean(props.displayRows && props.displayRows.length > 0))

const getRowData = (row) => (useRowProblem.value && row.problem ? row.problem : row)

const truncate = (str, maxLen = 28) => {
    if (!str) return ''
    const s = String(str).trim()
    return s.length <= maxLen ? s : s.slice(0, maxLen) + '…'
}

const columns = computed(() => {
    const base = [
    {
        key: 'internal_number',
        label: 'Nr.',
        sortable: true,
        value: (row) => getRowData(row).internal_number
    },
    {
        key: 'status',
        label: 'Būsena',
        sortable: true,
        value: (row) => props.statusLabels[getRowData(row).status] || getRowData(row).status
    },
    {
        key: 'created_at',
        label: 'Data',
        sortable: true,
        value: (row) => new Date(getRowData(row).created_at).toLocaleDateString('lt-LT')
    },
    {
        key: 'title',
        label: 'Pavadinimas',
        sortable: true,
        value: (row) => truncate(getRowData(row).title, 32)
    },
    {
        key: 'category',
        label: 'Kategorija',
        sortable: false,
        value: (row) => truncate(getRowData(row).category, 18)
    },
    {
        key: 'address',
        label: 'Adresas',
        sortable: false,
        value: (row) => truncate(getRowData(row).address, 28)
    },
    {
        key: 'description',
        label: 'Aprašymas',
        sortable: false,
        value: (row) => truncate(getRowData(row).description, 40)
    },
    ...(useRowProblem.value && props.canPublish
        ? [{
            key: 'publish',
            label: 'Viešai',
            sortable: false,
            cellComponent: ProblemTablePublishCell,
            cellProps: (row) => ({
                problem: getRowData(row),
                canPublish: props.canPublish
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

const handleRowDblClick = (row) => {
    const problem = row.problem || row
    router.visit(route('problems.show', problem.id))
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
        row-clickable
        @sort="emit('sort', $event)"
        @select-all="emit('select-all', $event)"
        @select-item="emit('select-item', $event)"
        @row-dblclick="handleRowDblClick"
    />
</template>

<style scoped>
:deep(.problem-table-merged-row) {
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
</style>
