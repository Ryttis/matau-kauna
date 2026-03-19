<script setup>
defineProps({
    problems: { type: Array, default: () => [] },
    displayRows: { type: Array, default: null },
    allCount: { type: Number, default: 0 },
    selectedIds: { type: Set, default: () => new Set() },
})

const emit = defineEmits(['select', 'open', 'select-group'])

function problemTitle(problem) {
    const text = (problem.description_preview || problem.description || '').trim()
    if (text) {
        return text.length > 60 ? text.slice(0, 60) + '…' : text
    }
    return problem.internal_number || `Problema #${problem.id}`
}

function rowSelected(selectedIds, problemId) {
    return selectedIds.has(problemId)
}

function onRowActivate(row) {
    if (row.groupIds?.length > 1) {
        emit('select-group', row.groupIds)
    } else {
        emit('select', row.problem.id)
    }
}

function onSimpleRowActivate(problem) {
    emit('select', problem.id)
}
</script>

<template>
    <ul class="problems-map-list">
        <template v-if="displayRows && displayRows.length">
            <li
                v-for="row in displayRows"
                :key="row.problem.id"
                class="problems-map-list__item"
                :class="{
                    'problems-map-list__item--selected': rowSelected(selectedIds, row.problem.id),
                    'problems-map-list__item--group-inner': row.groupIds?.length > 1 && !row.isLastInGroup,
                    'problems-map-list__item--group-end': row.isLastInGroup,
                }"
            >
                <div
                    class="problems-map-list__row-main"
                    role="button"
                    tabindex="0"
                    title="Pasirinkti"
                    @click="onRowActivate(row)"
                    @keydown.enter.prevent="onRowActivate(row)"
                    @keydown.space.prevent="onRowActivate(row)"
                >
                    <span class="problems-map-list__title">{{ problemTitle(row.problem) }}</span>
                </div>
                <button
                    type="button"
                    class="problems-map-list__view-btn"
                    @click.stop="emit('open', row.problem.id)"
                >
                    Peržiūrėti
                </button>
            </li>
        </template>
        <template v-else>
            <li
                v-for="problem in problems"
                :key="problem.id"
                class="problems-map-list__item"
                :class="{ 'problems-map-list__item--selected': rowSelected(selectedIds, problem.id) }"
            >
                <div
                    class="problems-map-list__row-main"
                    role="button"
                    tabindex="0"
                    title="Pasirinkti"
                    @click="onSimpleRowActivate(problem)"
                    @keydown.enter.prevent="onSimpleRowActivate(problem)"
                    @keydown.space.prevent="onSimpleRowActivate(problem)"
                >
                    <span class="problems-map-list__title">{{ problemTitle(problem) }}</span>
                </div>
                <button
                    type="button"
                    class="problems-map-list__view-btn"
                    @click.stop="emit('open', problem.id)"
                >
                    Peržiūrėti
                </button>
            </li>
        </template>
        <li v-if="(displayRows && displayRows.length === 0) || (!displayRows && !problems.length)" class="problems-map-list__item problems-map-list__item--empty">
            {{ allCount ? 'Pagal filtrą problemų nerasta' : 'Problemų nėra' }}
        </li>
    </ul>
</template>

<style scoped>
.problems-map-list {
    list-style: none;
    margin: 0;
    padding: 0 0 0.5rem;
}

.problems-map-list__item {
    display: flex;
    align-items: stretch;
    gap: 0.625rem;
    padding: 0.5rem 0.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.problems-map-list__item:last-child {
    border-bottom: none;
}

.problems-map-list__item--group-inner {
    border-bottom: none;
}

.problems-map-list__item--group-end {
    border-bottom: 2px dashed #e0e0e0;
}

.problems-map-list__item--selected {
    background: #1946a0;
    border-radius: 8px;
    border-bottom-color: transparent;
}

.problems-map-list__item--selected.problems-map-list__item--group-end {
    border-bottom: 2px dashed rgba(255, 255, 255, 0.35);
}

.problems-map-list__item--empty {
    color: #666;
    font-size: 0.9375rem;
    cursor: default;
    display: block;
}

.problems-map-list__row-main {
    flex: 1;
    min-width: 0;
    min-height: 2.2rem;
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 0.1rem 0;
    border-radius: 4px;
    box-sizing: border-box;
}

.problems-map-list__row-main:focus-visible {
    outline: 2px solid #1946a0;
    outline-offset: 2px;
}

.problems-map-list__item--selected .problems-map-list__row-main:focus-visible {
    outline-color: #fff;
}

.problems-map-list__title {
    display: block;
    font-size: 0.9375rem;
    color: #333;
}

.problems-map-list__item--selected .problems-map-list__title {
    color: #fff;
}

.problems-map-list__view-btn {
    flex-shrink: 0;
    align-self: center;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    border: 1px solid #1946a0;
    background: #fff;
    color: #1946a0;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}

.problems-map-list__view-btn:hover {
    background: #eef3fc;
}

.problems-map-list__item--selected .problems-map-list__view-btn {
    background: rgba(255, 255, 255, 0.2);
    border-color: #fff;
    color: #fff;
}

.problems-map-list__item--selected .problems-map-list__view-btn:hover {
    background: rgba(255, 255, 255, 0.35);
}
</style>
