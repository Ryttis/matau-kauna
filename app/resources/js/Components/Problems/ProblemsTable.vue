<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'

const props = defineProps({
    problems: {
        type: Array,
        required: true
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
    }
})

const emit = defineEmits(['sort', 'select-all', 'select-item'])

const columns = computed(() => [
    {
        key: 'internal_number',
        label: 'Nr.',
        sortable: true,
        value: (p) => p.internal_number
    },
    {
        key: 'status',
        label: 'Būsena',
        sortable: true,
        value: (p) => props.statusLabels[p.status] || p.status
    },
    {
        key: 'created_at',
        label: 'Data',
        sortable: true,
        value: (p) => new Date(p.created_at).toLocaleDateString('lt-LT')
    },
    {
        key: 'title',
        label: 'Pavadinimas',
        sortable: true,
        value: (p) => p.title
    },
    {
        key: 'category',
        label: 'Kategorija',
        sortable: false,
        value: (p) => p.category
    },
    {
        key: 'address',
        label: 'Adresas',
        sortable: false,
        value: (p) => p.address
    },
    {
        key: 'description',
        label: 'Aprašymas',
        sortable: false,
        value: (p) => p.description
    }
])

const handleRowDblClick = (problem) => {
    router.visit(route('problems.show', problem.id))
}
</script>

<template>
    <EntityTable
        :items="problems"
        :columns="columns"
        row-key="id"
        :sort-by="sortBy"
        :sort-direction="sortDirection"
        selectable
        :selected-keys="selectedProblems"
        row-clickable
        @sort="emit('sort', $event)"
        @select-all="emit('select-all', $event)"
        @select-item="emit('select-item', $event)"
        @row-dblclick="handleRowDblClick"
    />
</template>
