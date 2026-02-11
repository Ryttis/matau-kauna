<script setup>
import { ref, computed } from 'vue'
import { useKaunasMap } from '@/composables/useKaunasMap'

const props = defineProps({
    problems: { type: Array, default: () => [] },
    markerClass: { type: String, default: 'kaunas-map-marker' },
    showLegend: { type: Boolean, default: true },
    highlightedProblemId: { type: [Number, null], default: null },
    mergedProblemIds: { type: Array, default: () => [] },
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
})
</script>

<template>
    <div class="kaunas-map-wrap">
        <div ref="mapContainer" class="kaunas-map-container" />
        <div v-if="showLegend" class="kaunas-map-legend">
            <span class="kaunas-map-legend-item">
                <span class="kaunas-map-legend-dot kaunas-map-legend-dot--orange" />
                Naujos
            </span>
            <span class="kaunas-map-legend-item">
                <span class="kaunas-map-legend-dot kaunas-map-legend-dot--yellow" />
                Nagrinėjamos
            </span>
            <span class="kaunas-map-legend-item">
                <span class="kaunas-map-legend-dot kaunas-map-legend-dot--green" />
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

.kaunas-map-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: #333;
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

.kaunas-map-legend-dot--orange { background: #e67e22; }
.kaunas-map-legend-dot--yellow { background: #f1c40f; }
.kaunas-map-legend-dot--green { background: #27ae60; }
</style>
