<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        required: true
    },
    columns: {
        type: Array,
        required: true
    },
    rowKey: {
        type: [String, Function],
        default: 'id'
    },
    sortBy: {
        type: String,
        default: ''
    },
    sortDirection: {
        type: String,
        default: 'asc'
    },
    selectable: {
        type: Boolean,
        default: false
    },
    selectedKeys: {
        type: Array,
        default: () => []
    },
    showCheckbox: {
        type: [Function, Boolean],
        default: null
    },
    getSelectValue: {
        type: Function,
        default: null
    },
    singleSelect: {
        type: Boolean,
        default: false
    },
    rowClickable: {
        type: Boolean,
        default: false
    },
    rowClass: {
        type: Function,
        default: null
    }
})

const emit = defineEmits([
    'sort',
    'select-all',
    'select-item',
    'row-dblclick'
])

const selectAll = ref(false)

const getRowKey = (row) => {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row)
    }

    return row?.[props.rowKey]
}

const itemKeys = computed(() => props.items.map(getRowKey).filter(Boolean))

watch(
    () => props.selectedKeys,
    (newVal) => {
    if (!props.selectable || props.singleSelect) {
        selectAll.value = false
        return
    }

        if (props.items.length === 0) {
            selectAll.value = false
            return
        }

        selectAll.value = newVal.length === itemKeys.value.length
    },
    { immediate: true, deep: true }
)

watch(
    () => props.items,
    () => {
    if (!props.selectable || props.singleSelect) {
        selectAll.value = false
        return
    }

        if (props.items.length === 0) {
            selectAll.value = false
            return
        }

        selectAll.value = props.selectedKeys.length === itemKeys.value.length
    },
    { immediate: true }
)

const showRowCheckbox = (row) => {
    if (props.showCheckbox === null || props.showCheckbox === undefined) return true
    if (typeof props.showCheckbox === 'function') return props.showCheckbox(row)
    return !!props.showCheckbox
}

const getSelectValueForRow = (row) => {
    if (typeof props.getSelectValue === 'function') return props.getSelectValue(row)
    return getRowKey(row)
}

const isSelected = (row) => {
    const value = getSelectValueForRow(row)
    if (Array.isArray(value)) {
        return value.length > 0 && value.every((id) => props.selectedKeys.includes(id))
    }
    return props.selectedKeys.includes(value)
}

const handleSelectAll = () => {
    emit('select-all', !selectAll.value)
}

const handleSelectItem = (row) => {
    emit('select-item', getSelectValueForRow(row))
}

const handleSort = (column) => {
    if (!column.sortable) return
    emit('sort', column.key)
}

const handleRowDoubleClick = (row) => {
    emit('row-dblclick', row)
}

const sortArrow = (key) => {
    if (props.sortBy !== key) return ''
    return props.sortDirection === 'asc' ? '↑' : '↓'
}
</script>

<template>
    <table>
        <thead>
            <tr>
                <th
                    v-for="column in columns"
                    :key="column.key"
                    :class="[{ sortable: column.sortable }, column.headerClass]"
                    @click="handleSort(column)"
                >
                    {{ column.label }}
                    <span v-if="column.sortable && sortBy === column.key">
                        {{ sortArrow(column.key) }}
                    </span>
                </th>

                <th v-if="selectable && !singleSelect">
                    <input
                        type="checkbox"
                        :checked="selectAll"
                        @change="handleSelectAll"
                    />
                </th>
            </tr>
        </thead>

        <tbody>
            <tr
                v-for="row in items"
                :key="getRowKey(row)"
                :class="[{ 'row-clickable': rowClickable }, 'data-row', rowClass ? rowClass(row) : '']"
                @dblclick="handleRowDoubleClick(row)"
            >
                <td
                    v-for="column in columns"
                    :key="column.key"
                    :class="column.class"
                >
                    <component
                        v-if="column.cellComponent"
                        :is="column.cellComponent"
                        v-bind="column.cellProps ? column.cellProps(row) : {}"
                    />
                    <template v-else>
                        {{ column.value ? column.value(row) : row[column.key] }}
                    </template>
                </td>

                <td v-if="selectable">
                    <input
                        v-if="showRowCheckbox(row)"
                        type="checkbox"
                        :checked="isSelected(row)"
                        @change="handleSelectItem(row)"
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
    line-height: 1.4;
    letter-spacing: -1.1%;
    color: #1E1C1C;
    text-align: left;
    padding: 12px 10px;
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    vertical-align: middle;
    box-sizing: border-box;
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
    line-height: 1.4;
    letter-spacing: -1.1%;
    color: #1E1C1C;
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    vertical-align: middle;
    box-sizing: border-box;
}

tbody tr:hover {
    background: #f6f7f9;
}

.row-clickable {
    cursor: pointer;
}

input[type="checkbox"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
}
</style>
