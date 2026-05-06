<script setup>
import { MARKER_COLOR_BY_STATUS } from '@/constants/domain/problemStatus'

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
    cardLayout: { type: Boolean, default: false },
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

function showWatchButtonForRow(row) {
    if (!props.enableWatch) return false
    if (!row.groupIds || row.groupIds.length <= 1) return true
    return !!row.isGroupMain
}

function showWatchPlaceholderForRow(row) {
    if (!props.enableWatch) return false
    return row.groupIds?.length > 1 && !row.isGroupMain
}

function problemTitle(problem) {
    const text = (problem.description_preview || problem.description || '').trim()
    if (text) {
        return text.length > 60 ? text.slice(0, 60) + '…' : text
    }
    return problem.internal_number || `Problema #${problem.id}`
}

function cardTitle(problem) {
    const cat = (problem.category || '').trim()
    if (cat) return cat
    return problem.internal_number || `Problema #${problem.id}`
}

function statusDotColor(problem) {
    return MARKER_COLOR_BY_STATUS[problem?.status] || '#95a5a6'
}

function formatDate(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    return d.toLocaleDateString('lt-LT')
}

function shortAddress(addr) {
    if (!addr) return ''
    const s = String(addr).trim()
    return s.length > 65 ? s.slice(0, 65).trimEnd() + '…' : s
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
    <ul class="problems-map-list" :class="{ 'problems-map-list--cards': cardLayout }">
        <li v-if="actionsHeader && !cardLayout" class="problems-map-list__header">
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
                    'problems-map-list__item--card': cardLayout,
                }"
            >
                <template v-if="cardLayout">
                    <div
                        class="problems-map-list__card-body"
                        role="button"
                        tabindex="0"
                        title="Pasirinkti"
                        @click="onRowActivate(row)"
                        @keydown.enter.prevent="onRowActivate(row)"
                        @keydown.space.prevent="onRowActivate(row)"
                    >
                        <div class="problems-map-list__card-head">
                            <span
                                class="problems-map-list__status-dot"
                                :style="{ backgroundColor: statusDotColor(row.problem) }"
                                aria-hidden="true"
                            />
                            <span class="problems-map-list__card-title">{{ cardTitle(row.problem) }}</span>
                            <button
                                v-if="showWatchButtonForRow(row)"
                                type="button"
                                class="problems-map-list__watch-eye"
                                :class="{ 'problems-map-list__watch-eye--on': rowWatched(row.problem) }"
                                :disabled="watchBusy(row.problem)"
                                :data-tooltip="rowWatched(row.problem) ? 'Nebestebėti problemą' : 'Stebėti problemą'"
                                :aria-label="
                                    row.groupIds?.length > 1
                                        ? 'Stebėti arba nebestebėti visą sujungtą problemų grupę'
                                        : 'Stebėti arba nebestebėti problemą'
                                "
                                @click.stop="emit('toggle-watch', row.problem)"
                            >
                                <svg viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill="currentColor" d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z" />
                                    <path fill="currentColor" d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0" />
                                </svg>
                            </button>
                        </div>
                        <div v-if="row.problem.address" class="problems-map-list__card-meta">
                            <svg class="problems-map-list__card-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z" />
                            </svg>
                            <span class="problems-map-list__card-meta-text">{{ shortAddress(row.problem.address) }}</span>
                        </div>
                    </div>
                    <div class="problems-map-list__card-footer">
                        <span v-if="row.problem.created_at" class="problems-map-list__card-meta problems-map-list__card-meta--inline">
                            <svg class="problems-map-list__card-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z" />
                            </svg>
                            <span>{{ formatDate(row.problem.created_at) }}</span>
                        </span>
                        <button
                            type="button"
                            class="problems-map-list__view-btn problems-map-list__view-btn--internal problems-map-list__card-view-btn"
                            @click.stop="emit('open', row.problem.id)"
                        >
                            {{ viewButtonText }}
                        </button>
                    </div>
                </template>
                <template v-else>
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
                        <span
                            v-if="showWatchPlaceholderForRow(row)"
                            class="problems-map-list__watch-placeholder"
                            aria-hidden="true"
                        />
                        <button
                            v-if="showWatchButtonForRow(row)"
                            type="button"
                            class="problems-map-list__watch-btn problems-map-list__view-btn problems-map-list__view-btn--internal"
                            :class="{ 'problems-map-list__watch-btn--on': rowWatched(row.problem) }"
                            :disabled="watchBusy(row.problem)"
                            :title="rowWatched(row.problem) ? 'Nebestebėti' : 'Stebėti'"
                            :aria-label="
                                row.groupIds?.length > 1
                                    ? 'Stebėti arba nebestebėti visą sujungtą problemų grupę'
                                    : 'Stebėti arba nebestebėti problemą'
                            "
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
                </template>
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

.problems-map-list__watch-placeholder {
    flex-shrink: 0;
    min-width: 86px;
    align-self: center;
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

.problems-map-list--cards {
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.problems-map-list__item--card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 0.5rem 0.7rem 0.45rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    flex-direction: column;
    align-items: stretch;
    gap: 0.3rem;
}

.problems-map-list__item--card:last-child {
    border-bottom: 1px solid #e5e7eb;
}

.problems-map-list__item--card.problems-map-list__item--group-inner,
.problems-map-list__item--card.problems-map-list__item--group-end {
    border-bottom: 1px solid #e5e7eb;
}

.problems-map-list__card-body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    cursor: pointer;
    border-radius: 6px;
    min-height: 0;
}

.problems-map-list__card-body:focus-visible {
    outline: 2px solid #1946a0;
    outline-offset: 2px;
}

.problems-map-list__card-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.problems-map-list__status-dot {
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.problems-map-list__card-title {
    flex: 1;
    min-width: 0;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.problems-map-list__card-meta {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.8125rem;
    color: #6b7280;
    line-height: 1.3;
    margin-left: 1.125rem;
}

.problems-map-list__card-meta-text {
    word-break: break-word;
}

.problems-map-list__card-meta--inline {
    margin-left: 0;
    align-items: center;
}

.problems-map-list__card-icon {
    flex-shrink: 0;
    width: 14px;
    height: 14px;
    margin-top: 1px;
    color: #9ca3af;
}

.problems-map-list__card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-left: 1.125rem;
}

.problems-map-list__card-view-btn {
    margin-left: auto;
}

.problems-map-list__watch-eye {
    position: relative;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: none;
    background: transparent;
    color: #9ca3af;
    border-radius: 6px;
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease;
}

.problems-map-list__watch-eye[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    padding: 0.25rem 0.5rem;
    background: #1f2937;
    color: #fff;
    font-size: 0.75rem;
    line-height: 1.2;
    border-radius: 4px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    z-index: 20;
    transition: opacity 0.08s ease;
}

.problems-map-list__watch-eye[data-tooltip]:hover::after,
.problems-map-list__watch-eye[data-tooltip]:focus-visible::after {
    opacity: 1;
    visibility: visible;
}

.problems-map-list__watch-eye svg {
    width: 16px;
    height: 16px;
    display: block;
}

.problems-map-list__watch-eye:hover:not(:disabled) {
    background: #f1f5f9;
    color: #4b5563;
}

.problems-map-list__watch-eye:disabled {
    opacity: 0.45;
    cursor: wait;
}

.problems-map-list__watch-eye--on {
    color: var(--color-primary, #1946a0);
}

.problems-map-list__watch-eye--on:hover:not(:disabled) {
    background: #e8eef7;
    color: var(--color-primary-hover, #153a8a);
}

.problems-map-list__item--card.problems-map-list__item--selected {
    border-color: var(--color-primary, #1946a0);
    background: color-mix(in srgb, var(--color-primary, #1946a0) 8%, white);
}
</style>
