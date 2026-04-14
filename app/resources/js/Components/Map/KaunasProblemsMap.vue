<script setup>
import { ref, computed } from 'vue'
import { useKaunasMap } from '@/composables/useKaunasMap'
import {
    MAP_LEGEND_STATUS_ORDER,
    MARKER_COLOR_BY_STATUS,
    PROBLEM_STATUS_LABELS,
} from '@/constants/domain/problemStatus'
import { MAP_LEGEND_DOT_SIZE_PX } from '@/constants/ui/theme'

const props = defineProps({
    problems: { type: Array, default: () => [] },
    markerClass: { type: String, default: 'kaunas-map-marker' },
    showLegend: { type: Boolean, default: true },
    legendStatusOrder: { type: Array, default: null },
    highlightedProblemId: { type: [Number, null], default: null },
    mergedProblemIds: { type: Array, default: () => [] },
    autoFitView: { type: Boolean, default: true },
    focusHighlightedMarker: { type: Boolean, default: true },
})

const emit = defineEmits(['openProblem'])

const mapContainer = ref(null)
const problemsToShow = computed(() => props.problems || [])
const mergedIds = computed(() => props.mergedProblemIds || [])
const legendStatuses = computed(() => {
    if (Array.isArray(props.legendStatusOrder) && props.legendStatusOrder.length > 0) {
        return props.legendStatusOrder
    }
    return MAP_LEGEND_STATUS_ORDER
})

useKaunasMap(mapContainer, problemsToShow, {
    onProblemOpen: (id) => emit('openProblem', id),
    markerClassName: props.markerClass,
    highlightedProblemId: computed(() => props.highlightedProblemId),
    mergedProblemIds: mergedIds,
    autoFitView: computed(() => props.autoFitView),
    focusHighlightedMarker: computed(() => props.focusHighlightedMarker),
})
</script>

<template>
    <div class="kaunas-map-wrap">
        <div ref="mapContainer" class="kaunas-map-container" />
        <div v-if="showLegend" class="kaunas-map-legend">
            <span
                v-for="st in legendStatuses"
                :key="st"
                class="kaunas-map-legend-item"
            >
                <span
                    class="kaunas-map-legend-dot"
                    :style="{ background: MARKER_COLOR_BY_STATUS[st] || '#95a5a6' }"
                />
                {{ PROBLEM_STATUS_LABELS[st] }}
            </span>
        </div>
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
    gap: 0.25rem;
    max-height: 220px;
    overflow-y: auto;
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
    display: block;
    padding: 5px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    line-height: 1.35;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster .kaunas-map-popup-open-problem:last-of-type) {
    border-bottom: none;
    padding-bottom: 0;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-line-num) {
    font-weight: 600;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster-line-rest) {
    font-size: 12px;
    color: #555;
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

.kaunas-map-container :deep(.kaunas-map-popup-more) {
    font-size: 0.875rem;
    color: var(--color-text-muted, #666);
}

.kaunas-map-container :deep(.leaflet-marker-icon.kaunas-map-marker--cluster) {
    background: transparent !important;
    border: none !important;
}

.kaunas-map-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem 1.25rem;
    align-items: center;
    justify-content: flex-start;
    padding: 1rem 1.5rem;
    font-size: 0.8125rem;
    color: var(--color-text, #333);
}

.kaunas-map-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.kaunas-map-legend-dot {
    display: block;
    flex-shrink: 0;
    box-sizing: border-box;
    width: var(--map-legend-dot-size, 12px);
    height: var(--map-legend-dot-size, 12px);
    min-width: var(--map-legend-dot-size, 12px);
    min-height: var(--map-legend-dot-size, 12px);
    max-width: var(--map-legend-dot-size, 12px);
    max-height: var(--map-legend-dot-size, 12px);
    border-radius: 50%;
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
