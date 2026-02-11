<template>
    <section class="landing-map">
        <div ref="mapContainer" class="landing-map-container" />
        <div class="landing-map-legend">
            <span class="landing-map-legend-item">
                <span class="landing-map-legend-dot landing-map-legend-dot--orange" />
                Naujos
            </span>
            <span class="landing-map-legend-item">
                <span class="landing-map-legend-dot landing-map-legend-dot--yellow" />
                Nagrinėjamos
            </span>
            <span class="landing-map-legend-item">
                <span class="landing-map-legend-dot landing-map-legend-dot--green" />
                Išspręstos
            </span>
        </div>
    </section>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { loadLeaflet, createBaseMap, MAP_MAX_ZOOM } from '@/composables/leafletKaunas'
import { getMarkerColorForStatus } from '@/constants/problemStatus'

const props = defineProps({
    problems: { type: Array, default: () => [] },
})

const mapContainer = ref(null)
let map = null
let mapInitialized = false
let markersLayer = null

function updateMarkers() {
    if (!map || !markersLayer || typeof L === 'undefined') return
    markersLayer.clearLayers()
    const list = props.problems || []
    list.forEach((p) => {
        const color = getMarkerColorForStatus(p.status)
        const icon = L.divIcon({
            className: 'landing-map-marker',
            html: `<span style="background-color:${color};width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7],
        })
        L.marker([p.lat, p.lng], { icon })
            .bindPopup(`<strong>${p.internal_number}</strong><br>${p.title}`)
            .addTo(markersLayer)
    })
}

function initMap() {
    if (mapInitialized || !mapContainer.value || typeof L === 'undefined') return
    map = createBaseMap(mapContainer.value, { maxZoom: MAP_MAX_ZOOM })
    if (!map) return
    markersLayer = L.layerGroup().addTo(map)
    mapInitialized = true
    updateMarkers()
}

watch(() => props.problems, () => updateMarkers(), { deep: true })

onMounted(() => {
    loadLeaflet().then(() => nextTick(() => setTimeout(initMap, 100)))
})

onUnmounted(() => {
    if (map) {
        map.remove()
        map = null
        markersLayer = null
        mapInitialized = false
    }
})
</script>

<style scoped>
.landing-map {
    background: #fafafa;
}

.landing-map-container {
    width: 100%;
    height: 400px;
}

.landing-map-container :deep(.leaflet-marker-icon.landing-map-marker) {
    background: transparent !important;
    border: none !important;
}

.landing-map-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    padding: 1rem 1.5rem 2rem;
    font-size: 0.875rem;
    color: var(--lp-text-muted, #333);
}

.landing-map-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.landing-map-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.landing-map-legend-dot--orange {
    background: #e67e22;
}

.landing-map-legend-dot--yellow {
    background: #f1c40f;
}

.landing-map-legend-dot--green {
    background: #27ae60;
}
</style>
