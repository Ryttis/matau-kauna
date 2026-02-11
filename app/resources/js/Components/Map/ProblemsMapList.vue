<script setup>
defineProps({
    problems: { type: Array, default: () => [] },
    displayRows: { type: Array, default: null },
    allCount: { type: Number, default: 0 },
    selectedIds: { type: Set, default: () => new Set() },
})

const emit = defineEmits(['select', 'open', 'select-group'])

function problemTitle(problem) {
    return problem.title || problem.internal_number || `Problema #${problem.id}`
}

function toggle(problemId, event) {
    event?.stopPropagation()
    emit('select', problemId)
}

function toggleGroup(groupIds, event) {
    event?.stopPropagation()
    emit('select-group', groupIds)
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
                    'problems-map-list__item--group-inner': row.groupIds?.length > 1 && !row.isLastInGroup,
                    'problems-map-list__item--group-end': row.isLastInGroup,
                }"
                title="Dukart spustelėkite – problemos peržiūra"
                @dblclick="emit('open', row.problem.id)"
            >
                <label class="problems-map-list__label">
                    <span class="problems-map-list__title">{{ problemTitle(row.problem) }}</span>
                    <input
                        v-if="row.isGroupMain"
                        type="checkbox"
                        :checked="row.groupIds && row.groupIds.every((id) => selectedIds.has(id))"
                        class="problems-map-list__checkbox"
                        @change="toggleGroup(row.groupIds, $event)"
                    />
                    <span v-else class="problems-map-list__checkbox-placeholder" />
                </label>
            </li>
        </template>
        <template v-else>
            <li
                v-for="problem in problems"
                :key="problem.id"
                class="problems-map-list__item"
                title="Dukart spustelėkite – problemos peržiūra"
                @dblclick="emit('open', problem.id)"
            >
                <label class="problems-map-list__label">
                    <span class="problems-map-list__title">{{ problemTitle(problem) }}</span>
                    <input
                        type="checkbox"
                        :checked="selectedIds.has(problem.id)"
                        class="problems-map-list__checkbox"
                        @change="toggle(problem.id, $event)"
                    />
                </label>
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
    padding: 0.75rem 0;
    border-bottom: 1px solid #e0e0e0;
    cursor: pointer;
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

.problems-map-list__checkbox-placeholder {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    display: inline-block;
}

.problems-map-list__item--empty {
    color: #666;
    font-size: 0.9375rem;
    cursor: default;
}

.problems-map-list__label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.problems-map-list__title {
    flex: 1;
    font-size: 0.9375rem;
    color: #333;
}

.problems-map-list__checkbox {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}
</style>
