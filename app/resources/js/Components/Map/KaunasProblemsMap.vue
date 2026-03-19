<script setup>
import { ref, computed } from 'vue'
import { useKaunasMap } from '@/composables/useKaunasMap'
import { PROBLEM_STATUS, MARKER_COLOR_BY_STATUS } from '@/constants/problemStatus'

const props = defineProps({
    problems: { type: Array, default: () => [] },
    markerClass: { type: String, default: 'kaunas-map-marker' },
    showLegend: { type: Boolean, default: true },
    highlightedProblemId: { type: [Number, null], default: null },
    mergedProblemIds: { type: Array, default: () => [] },
    autoFitView: { type: Boolean, default: true },
    focusHighlightedMarker: { type: Boolean, default: true },
})

const emit = defineEmits(['openProblem'])

const mapContainer = ref(null)
const problemsToShow = computed(() => props.problems || [])
const mergedIds = computed(() => props.mergedProblemIds || [])

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
            <span class="kaunas-map-legend-item">
                <span
                    class="kaunas-map-legend-dot"
                    :style="{ background: MARKER_COLOR_BY_STATUS[PROBLEM_STATUS.NEW] }"
                />
                Naujos
            </span>
            <span class="kaunas-map-legend-item">
                <span
                    class="kaunas-map-legend-dot"
                    :style="{ background: MARKER_COLOR_BY_STATUS[PROBLEM_STATUS.IN_REVIEW] }"
                />
                Nagrinėjamos
            </span>
            <span class="kaunas-map-legend-item">
                <span
                    class="kaunas-map-legend-dot"
                    :style="{ background: MARKER_COLOR_BY_STATUS[PROBLEM_STATUS.RESOLVED] }"
                />
                Išspręstos
            </span>
        </div>
    </div>
</template>

<style scoped>
.kaunas-map-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #fafafa;
}

.kaunas-map-container {
    flex: 1;
    min-height: 280px;
    width: 100%;
}

.kaunas-map-container :deep(.leaflet-marker-icon.kaunas-map-marker) {
    background: transparent !important;
    border: none !important;
}

.kaunas-map-container :deep(.kaunas-map-popup-cluster) {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-height: 200px;
    overflow-y: auto;
}

.kaunas-map-container :deep(.kaunas-map-popup-open-problem) {
    color: var(--color-primary, #1946A0);
    text-decoration: none;
}

.kaunas-map-container :deep(.kaunas-map-popup-open-problem:hover) {
    text-decoration: underline;
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
    gap: 1rem 1.5rem;
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: var(--color-text, #333);
}

.kaunas-map-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.kaunas-map-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
</style>
