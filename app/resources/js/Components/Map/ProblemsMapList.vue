<script setup>
const props = defineProps({
    problems: { type: Array, default: () => [] },
    displayRows: { type: Array, default: null },
    allCount: { type: Number, default: 0 },
    selectedIds: { type: Set, default: () => new Set() },
    enableWatch: { type: Boolean, default: false },
    isWatched: {
        type: Function,
        default: null,
    },
    watchLoadingRootId: { type: Number, default: null },
    actionsHeader: { type: String, default: '' },
    viewButtonText: { type: String, default: 'Peržiūrėti' },
    viewButtonVariant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'internal'].includes(v),
    },
})

const emit = defineEmits(['select', 'open', 'select-group', 'toggle-watch'])

function watchRoot(problem) {
    return problem?.merged_into_problem_id ?? problem?.id
}

function rowWatched(problem) {
    return props.isWatched ? !!props.isWatched(problem) : false
}

function watchBusy(problem) {
    const root = watchRoot(problem)
    return props.watchLoadingRootId != null && props.watchLoadingRootId === root
}

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
        <li v-if="actionsHeader" class="problems-map-list__header">
            <span class="problems-map-list__header-main" />
            <span class="problems-map-list__header-actions">{{ actionsHeader }}</span>
        </li>
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
                <div class="problems-map-list__row-actions">
                    <button
                        v-if="enableWatch"
                        type="button"
                        class="problems-map-list__watch-btn problems-map-list__view-btn problems-map-list__view-btn--internal"
                        :class="{ 'problems-map-list__watch-btn--on': rowWatched(row.problem) }"
                        :disabled="watchBusy(row.problem)"
                        :title="rowWatched(row.problem) ? 'Nebestebėti' : 'Stebėti'"
                        aria-label="Stebėti arba nebestebėti problemą"
                        @click.stop="emit('toggle-watch', row.problem)"
                    >
                        {{ rowWatched(row.problem) ? 'Stebima' : 'Stebėti' }}
                    </button>
                    <button
                        type="button"
                        class="problems-map-list__view-btn"
                        :class="{
                            'problems-map-list__view-btn--internal': viewButtonVariant === 'internal',
                        }"
                        @click.stop="emit('open', row.problem.id)"
                    >
                        {{ viewButtonText }}
                    </button>
                </div>
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
                <div class="problems-map-list__row-actions">
                    <button
                        v-if="enableWatch"
                        type="button"
                        class="problems-map-list__watch-btn problems-map-list__view-btn problems-map-list__view-btn--internal"
                        :class="{ 'problems-map-list__watch-btn--on': rowWatched(problem) }"
                        :disabled="watchBusy(problem)"
                        :title="rowWatched(problem) ? 'Nebestebėti' : 'Stebėti'"
                        aria-label="Stebėti arba nebestebėti problemą"
                        @click.stop="emit('toggle-watch', problem)"
                    >
                        {{ rowWatched(problem) ? 'Stebima' : 'Stebėti' }}
                    </button>
                    <button
                        type="button"
                        class="problems-map-list__view-btn"
                        :class="{
                            'problems-map-list__view-btn--internal': viewButtonVariant === 'internal',
                        }"
                        @click.stop="emit('open', problem.id)"
                    >
                        {{ viewButtonText }}
                    </button>
                </div>
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
    gap: 0.5rem;
    padding: 0.5rem 0.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.problems-map-list__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.5rem 0.45rem;
    border-bottom: 1px solid #e0e0e0;
}

.problems-map-list__header-main {
    flex: 1;
    min-width: 0;
}

.problems-map-list__header-actions {
    flex-shrink: 0;
    font-size: 0.9375rem;
    font-weight: 600;
    line-height: 1.2;
    color: #333;
}

.problems-map-list__watch-btn {
    min-width: 86px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
}

.problems-map-list__watch-btn:hover:not(:disabled) {
    background: #e8eef7;
}

.problems-map-list__watch-btn:disabled {
    opacity: 0.45;
    cursor: wait;
}

.problems-map-list__item--selected .problems-map-list__watch-btn:hover:not(:disabled) {
    background: rgba(25, 70, 160, 0.12);
}

.problems-map-list__watch-btn--on {
    background: #1946a0;
    color: #fff;
    border-color: #1946a0;
}

.problems-map-list__watch-btn--on:hover:not(:disabled) {
    background: #153a8a;
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
    background: color-mix(in srgb, var(--color-primary, #1946a0) 38%, white);
    border-radius: 8px;
    border-bottom-color: transparent;
}

.problems-map-list__item--selected.problems-map-list__item--group-end {
    border-bottom: 2px dashed color-mix(in srgb, var(--color-primary, #1946a0) 30%, white);
}

.problems-map-list__item--empty {
    color: #666;
    font-size: 0.9375rem;
    cursor: default;
    display: block;
}

.problems-map-list__row-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
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
    outline-color: #1946a0;
}

.problems-map-list__title {
    display: block;
    font-size: 0.9375rem;
    color: #333;
}

.problems-map-list__item--selected .problems-map-list__title {
    color: #0a1732;
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

.problems-map-list__view-btn--internal {
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 400;
    background: #fff;
    color: var(--color-primary, #1946a0);
    border-color: var(--color-primary, #1946a0);
}

.problems-map-list__view-btn--internal:hover {
    background: #e8eef7;
}

.problems-map-list__view-btn:hover {
    background: #eef3fc;
}

.problems-map-list__item--selected .problems-map-list__view-btn {
    background: #fff;
    border-color: #1946a0;
    color: #1946a0;
}

.problems-map-list__item--selected .problems-map-list__view-btn:hover {
    background: #e8eef7;
}

.problems-map-list__watch-btn.problems-map-list__watch-btn--on {
    background: var(--color-primary, #1946a0);
    color: #fff;
    border-color: var(--color-primary, #1946a0);
}

.problems-map-list__watch-btn.problems-map-list__watch-btn--on:hover:not(:disabled) {
    background: var(--color-primary-hover, #153a8a);
    border-color: var(--color-primary-hover, #153a8a);
}
</style>
