<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'

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

const handleRowDoubleClick = (problemId) => {
    router.visit(route('problems.show', problemId))
}

const selectAll = ref(false)

watch(() => props.selectedProblems, (newVal) => {
    if (props.problems.length > 0) {
        selectAll.value = newVal.length === props.problems.length && props.problems.length > 0
    } else {
        selectAll.value = false
    }
}, { immediate: true, deep: true })

watch(() => props.problems, () => {
    if (props.problems.length > 0) {
        selectAll.value = props.selectedProblems.length === props.problems.length && props.problems.length > 0
    } else {
        selectAll.value = false
    }
}, { immediate: true })

const handleSelectAll = () => {
    emit('select-all', !selectAll.value)
}

const handleSelectItem = (problemId) => {
    emit('select-item', problemId)
}

const handleSort = (field) => {
    emit('sort', field)
}

const isSelected = (problemId) => {
    return props.selectedProblems.includes(problemId)
}
</script>

<template>
    <table>
        <thead>
            <tr>
                <th @click="handleSort('internal_number')" class="sortable">
                    Nr.
                    <span v-if="sortBy === 'internal_number'">
                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                </th>
                <th @click="handleSort('status')" class="sortable">
                    Būsena
                    <span v-if="sortBy === 'status'">
                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                </th>
                <th @click="handleSort('created_at')" class="sortable">
                    Data
                    <span v-if="sortBy === 'created_at'">
                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                </th>
                <th @click="handleSort('title')" class="sortable">
                    Pavadinimas
                    <span v-if="sortBy === 'title'">
                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                    </span>
                </th>
                <th>Kategorija</th>
                <th>Adresas</th>
                <th>Aprašymas</th>
                <th>
                    <AppCheckbox
                        :model-value="selectAll"
                        @change="handleSelectAll"
                    />
                </th>
            </tr>
        </thead>

        <tbody>
            <tr
                v-for="problem in problems"
                :key="problem.id"
                @dblclick="handleRowDoubleClick(problem.id)"
                class="problem-row"
            >
                <td>{{ problem.internal_number }}</td>
                <td>{{ statusLabels[problem.status] || problem.status }}</td>
                <td>{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</td>
                <td>{{ problem.title }}</td>
                <td>{{ problem.category }}</td>
                <td>{{ problem.address }}</td>
                <td>{{ problem.description }}</td>
                <td>
                    <AppCheckbox
                        :model-value="isSelected(problem.id)"
                        @change="handleSelectItem(problem.id)"
                        @click.stop
                    />
                </td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
table {
    width: 100%;
    margin-top: 40px;
    font-family: 'Inter', sans-serif;
    border-collapse: collapse;
}

th {
    font-size: 12px;
    font-weight: 400;
    line-height: 150%;
    letter-spacing: -1.1%;
    color: #1E1C1C;
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
}

th.sortable {
    cursor: pointer;
    user-select: none;
}

th.sortable:hover {
    background-color: #f6f7f9;
}

td {
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 400;
    line-height: 150%;
    letter-spacing: -1.1%;
    color: #1E1C1C;
    border-bottom: 1px solid #f0f0f0;
}

tbody tr:hover {
    background: #f6f7f9;
}

.problem-row {
    cursor: pointer;
}

</style>
