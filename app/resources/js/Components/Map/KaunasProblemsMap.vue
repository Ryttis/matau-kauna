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
    navigableGroups: { type: Array, default: () => [] },
})

const emit = defineEmits(['openProblem'])

const mapContainer = ref(null)
const problemsToShow = computed(() => props.problems || [])
const mergedIds = computed(() => props.mergedProblemIds || [])
const navigableGroupsList = computed(() => props.navigableGroups || [])

useKaunasMap(mapContainer, problemsToShow, {
    onProblemOpen: (id) => emit('openProblem', id),
    markerClassName: props.markerClass,
    highlightedProblemId: computed(() => props.highlightedProblemId),
    mergedProblemIds: mergedIds,
    navigableGroupsRef: navigableGroupsList,
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

.kaunas-map-container :deep(.kaunas-map-popup-nav) {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.4rem;
    padding-top: 0.4rem;
    border-top: 1px solid #eee;
}

.kaunas-map-container :deep(.kaunas-map-popup-nav-btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    min-width: 24px;
    min-height: 24px;
    padding: 0;
    line-height: 1;
    color: var(--color-primary, #1946A0);
    background: #f0f4ff;
    border: 1px solid #c8d4f0;
    border-radius: 4px;
    cursor: pointer;
}

.kaunas-map-container :deep(.kaunas-map-popup-nav-btn:hover:not(.kaunas-map-popup-nav-btn--disabled)) {
    background: #e0e8ff;
}

.kaunas-map-container :deep(.kaunas-map-popup-nav-btn--disabled) {
    background: #f5f5f5;
    border-color: #e0e0e0;
    cursor: default;
}

.kaunas-map-container :deep(.kaunas-map-popup-nav-btn--disabled .kaunas-map-popup-nav-icon) {
    opacity: 0.4;
}

.kaunas-map-container :deep(.kaunas-map-popup-nav-icon) {
    width: 12px;
    height: 12px;
    display: block;
    pointer-events: none;
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
