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
    hideRowCheckboxes: {
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

const safeItems = computed(() => (Array.isArray(props.items) ? props.items : []))

const getRowKey = (row) => {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row)
    }

    return row?.[props.rowKey]
}

const itemKeys = computed(() => safeItems.value.map(getRowKey).filter(Boolean))

watch(
    () => props.selectedKeys,
    (newVal) => {
        if (!props.selectable || props.singleSelect) {
            selectAll.value = false
            return
        }

        if (safeItems.value.length === 0) {
            selectAll.value = false
            return
        }

        selectAll.value = newVal.length === itemKeys.value.length
    },
    { immediate: true, deep: true }
)

watch(
    () => safeItems.value,
    () => {
        if (!props.selectable || props.singleSelect) {
            selectAll.value = false
            return
        }

        if (safeItems.value.length === 0) {
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

const selectionByRowClick = computed(
    () => props.hideRowCheckboxes && props.selectable && props.rowClickable
)

const handleRowClick = (row, event) => {
    if (!selectionByRowClick.value) return
    if (event.detail > 1) return
    if (event.target.closest('button, a, input, textarea, select, label')) return
    handleSelectItem(row)
}

const sortArrow = (key) => {
    if (props.sortBy !== key) return ''
    return props.sortDirection === 'asc' ? '↑' : '↓'
}
</script>

<template>
    <div class="entity-table-scroll">
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
                v-for="row in safeItems"
                :key="getRowKey(row)"
                :class="[
                    { 'row-clickable': rowClickable, 'row-selected': hideRowCheckboxes && isSelected(row) },
                    'data-row',
                    rowClass ? rowClass(row) : ''
                ]"
                @click="handleRowClick(row, $event)"
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

                <td v-if="selectable" class="entity-table-select-cell">
                    <input
                        v-if="!hideRowCheckboxes && showRowCheckbox(row)"
                        type="checkbox"
                        :checked="isSelected(row)"
                        @change="handleSelectItem(row)"
                        @click.stop
                    />
                </td>
            </tr>
        </tbody>
    </table>
    </div>
</template>

<style scoped>
.entity-table-scroll {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-top: 8px;
    padding-bottom: 4px;
    scrollbar-gutter: stable;
}

table {
    width: max-content;
    min-width: 100%;
    margin-top: 16px;
    font-family: 'Open Sans', sans-serif;
    border-collapse: collapse;
    table-layout: auto;
}

@media (min-width: 1024px) {
    table {
        margin-top: 24px;
    }

    .entity-table-scroll {
        margin-top: 0;
    }
}

th {
    font-size: var(--table-header-font-size);
    font-weight: var(--table-header-font-weight);
    line-height: 1.35;
    letter-spacing: normal;
    color: var(--table-header-text);
    background: var(--table-header-bg);
    text-align: left;
    padding: 12px 10px;
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    vertical-align: middle;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    td {
        padding: 12px 10px;
        font-size: 12px;
    }
}

th.sortable {
    cursor: pointer;
    user-select: none;
}

th.sortable:hover {
    background-color: var(--table-header-hover-bg);
}

td {
    padding: 10px 8px;
    font-size: 13px;
    font-weight: 400;
    line-height: 1.35;
    letter-spacing: normal;
    color: var(--table-header-text);
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    vertical-align: middle;
    box-sizing: border-box;
}

tbody tr:hover {
    background: var(--color-bg-subtle);
}

tbody tr.row-selected {
    background: color-mix(in srgb, var(--color-primary, #1946a0) 38%, white);
}

tbody tr.row-selected:hover {
    background: color-mix(in srgb, var(--color-primary, #1946a0) 46%, white);
}

.row-clickable {
    cursor: pointer;
}

.entity-table-select-cell {
    width: 44px;
    box-sizing: border-box;
}

input[type="checkbox"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
}
</style>
