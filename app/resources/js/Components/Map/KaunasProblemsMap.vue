<script setup>
import { ref, computed } from 'vue'
import { useKaunasMap } from '@/composables/useKaunasMap'

const props = defineProps({
    problems: { type: Array, default: () => [] },
    markerClass: { type: String, default: 'kaunas-map-marker' },
    highlightedProblemId: { type: [Number, null], default: null },
    mergedProblemIds: { type: Array, default: () => [] },
    markableProblemIds: { type: Array, default: () => [] },
    selectedProblemIds: { type: Array, default: () => [] },
    autoFitView: { type: Boolean, default: true },
    focusHighlightedMarker: { type: Boolean, default: true },
})

const emit = defineEmits(['openProblem', 'markProblem'])

const mapContainer = ref(null)
const problemsToShow = computed(() => props.problems || [])
const mergedIds = computed(() => props.mergedProblemIds || [])
const markableIds = computed(() => props.markableProblemIds || [])
const selectedIds = computed(() => props.selectedProblemIds || [])

useKaunasMap(mapContainer, problemsToShow, {
    onProblemOpen: (id) => emit('openProblem', id),
    onProblemMark: (id) => emit('markProblem', id),
    markerClassName: props.markerClass,
    highlightedProblemId: computed(() => props.highlightedProblemId),
    mergedProblemIds: mergedIds,
    markableProblemIds: markableIds,
    selectedProblemIds: selectedIds,
    autoFitView: computed(() => props.autoFitView),
    focusHighlightedMarker: computed(() => props.focusHighlightedMarker),
})
</script>

<template>
    <div class="kaunas-map-wrap">
        <div ref="mapContainer" class="kaunas-map-container" />
    </div>
</template>

<style scoped>
.kaunas-map-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    background: #fafafa;
}

.kaunas-map-container {
    flex: 1;
    min-height: 280px;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    position: relative;
    overflow: hidden;
}

.kaunas-map-container :deep(.leaflet-control-container),
.kaunas-map-container :deep(.leaflet-top),
.kaunas-map-container :deep(.leaflet-bottom) {
    z-index: 400;
}

.kaunas-map-container :deep(.leaflet-marker-icon.kaunas-map-marker) {
    background: transparent !important;
    border: none !important;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster) {
    display: flex;
    flex-direction: column;
    max-height: 260px;
    overflow-y: auto;
    min-width: 280px;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-item) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 7px 2px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    min-width: 0;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-item:last-of-type) {
    border-bottom: none;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-item:hover) {
    background: rgba(25, 70, 160, 0.04);
}

.kaunas-map-container :deep(.kaunas-map-popup-body) {
    min-width: 0;
}

.kaunas-map-container :deep(.kaunas-map-popup-title) {
    display: block;
    font-size: 1rem;
    margin-bottom: 6px;
}

.kaunas-map-container :deep(.kaunas-map-popup-meta) {
    font-size: 12px;
    line-height: 1.4;
    color: var(--color-text-muted, #555);
    margin: 0 0 8px;
}

.kaunas-map-container :deep(.kaunas-map-popup-row) {
    margin: 2px 0;
}

.kaunas-map-container :deep(.kaunas-map-popup-k) {
    display: inline-block;
    min-width: 5.5rem;
    color: var(--color-text-muted, #666);
    font-weight: 600;
}

.kaunas-map-container :deep(.kaunas-map-popup-desc) {
    margin: 0;
    font-size: 13px;
    line-height: 1.45;
    color: var(--color-text, #222);
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster .kaunas-map-popup-open-problem) {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
    line-height: 1.35;
    padding: 2px 0;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-line-num) {
    font-weight: 600;
    font-size: 13px;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-line-rest) {
    font-size: 12px;
    color: #555;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
    min-width: 0;
}

.kaunas-map-container :deep(.kaunas-map-popup-more-wrap) {
    text-align: center;
    padding: 6px 0 2px;
}

.kaunas-map-container :deep(.leaflet-popup-content .kaunas-map-popup-open-problem),
.kaunas-map-container :deep(.leaflet-popup-content .kaunas-map-popup-open-problem:hover),
.kaunas-map-container :deep(.leaflet-popup-content .kaunas-map-popup-open-problem:visited),
.kaunas-map-container :deep(.leaflet-popup-content .kaunas-map-popup-open-problem:focus) {
    color: var(--color-primary, #1946a0);
    text-decoration: none !important;
}

.kaunas-map-container :deep(.leaflet-popup-content .kaunas-map-popup-open-problem:hover) {
    opacity: 0.88;
}

.kaunas-map-container :deep(.kaunas-map-popup-actions) {
    margin: 10px 0 0;
    text-align: left;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.kaunas-map-container :deep(.kaunas-map-popup-view-btn) {
    padding: 5px 12px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid var(--color-primary, #1946a0);
    border-radius: 6px;
    background: #fff;
    color: var(--color-primary, #1946a0);
}

.kaunas-map-container :deep(.kaunas-map-popup-view-btn:hover) {
    opacity: 0.9;
}

.kaunas-map-container :deep(.kaunas-map-popup-view-btn--sm) {
    padding: 3px 9px;
    font-size: 12px;
    flex-shrink: 0;
    white-space: nowrap;
}

.kaunas-map-container :deep(.kaunas-map-popup-view-btn.is-selected) {
    background: var(--color-primary, #1946a0);
    color: #fff;
}

.kaunas-map-container :deep(.kaunas-map-popup-view-btn.is-selected:hover) {
    opacity: 0.92;
}

.kaunas-map-container :deep(.kaunas-map-popup-more) {
    font-size: 0.875rem;
    color: var(--color-text-muted, #666);
}

.kaunas-map-container :deep(.leaflet-marker-icon.kaunas-map-marker--cluster) {
    background: transparent !important;
    border: none !important;
}

@media (max-width: 767px) {
    .kaunas-map-container :deep(.leaflet-top.leaflet-right) {
        top: 8px;
        right: 8px;
    }

    .kaunas-map-container :deep(.leaflet-top.leaflet-right .leaflet-control) {
        margin-top: 0;
        margin-right: 0;
    }
}
</style>
