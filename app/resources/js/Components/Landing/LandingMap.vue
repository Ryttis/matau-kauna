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
                Išnagrinėtos
            </span>
        </div>
    </section>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/utils/leafletKaunas'
import { getMarkerColorForStatus } from '@/constants/domain/problemStatus'
import { MAP_CLUSTER_COLOR } from '@/constants/ui/theme'
import { clusterProblems } from '@/utils/mapClustering'

const CLUSTER_SIZE = 28

function escapeHtml(s) {
    return (s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function clusterPopupContent(problems) {
    const lines = (problems || []).slice(0, 12).map((p) => {
        const num = escapeHtml(p.internal_number || '')
        const preview = escapeHtml((p.description_preview || p.description || '').slice(0, 40))
        return `${num} ${preview}`
    })
    const rest = problems.length > 12 ? problems.length - 12 : 0
    const restLine = rest > 0 ? `+${rest}` : ''
    return lines.join('<br>') + (restLine ? `<br><span style="color:#666">${restLine}</span>` : '')
}

const props = defineProps({
    problems: { type: Array, default: () => [] },
})

const mapContainer = ref(null)
let map = null
let mapInitialized = false
let markersLayer = null
let removeZoomListener = null

function updateMarkers(skipFitView = false) {
    if (!map || !markersLayer || typeof L === 'undefined') return
    const list = Array.isArray(props.problems) ? props.problems : []
    const zoom = map.getZoom()
    const items = clusterProblems(list, zoom)
    markersLayer.clearLayers()
    items.forEach((item) => {
        if (item.type === 'single') {
            const p = item.problem
            const lat = Number(p.lat)
            const lng = Number(p.lng)
            if (Number.isNaN(lat) || Number.isNaN(lng)) return
            const color = getMarkerColorForStatus(p.status)
            const icon = L.divIcon({
                className: 'landing-map-marker',
                html: `<span style="background-color:${color};width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7],
            })
            L.marker([lat, lng], { icon })
                .bindPopup(`<strong>${escapeHtml(p.internal_number)}</strong><br>${escapeHtml((p.description_preview || p.description || '').slice(0, 200))}`)
                .addTo(markersLayer)
        } else {
            const { lat, lng, count, problems: clusterProblemsList } = item
            const label = count >= 9 ? '+9' : `+${count}`
            const icon = L.divIcon({
                className: 'landing-map-marker landing-map-marker--cluster',
                html: `<span style="background-color:${MAP_CLUSTER_COLOR};width:${CLUSTER_SIZE}px;height:${CLUSTER_SIZE}px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;box-sizing:border-box;">${label}</span>`,
                iconSize: [CLUSTER_SIZE, CLUSTER_SIZE],
                iconAnchor: [CLUSTER_SIZE / 2, CLUSTER_SIZE / 2],
            })
            L.marker([lat, lng], { icon })
                .bindPopup(clusterPopupContent(clusterProblemsList))
                .addTo(markersLayer)
        }
    })
    if (!skipFitView) {
        if (list.length === 1) {
            map.setView([list[0].lat, list[0].lng], 14)
        } else if (list.length > 1) {
            const bounds = L.latLngBounds(list.map((p) => [Number(p.lat), Number(p.lng)]))
            map.fitBounds(bounds.pad(0.1))
        } else {
            map.setView(KAUNAS_CENTER, 12)
        }
    }
}

function initMap() {
    if (mapInitialized || !mapContainer.value || typeof L === 'undefined') return
    map = createBaseMap(mapContainer.value, { center: KAUNAS_CENTER, zoom: 12, maxZoom: MAP_MAX_ZOOM })
    if (!map) return
    markersLayer = L.layerGroup().addTo(map)
    mapInitialized = true
    const zoomHandler = () => updateMarkers(true)
    map.on('zoomend', zoomHandler)
    removeZoomListener = () => { if (map) map.off('zoomend', zoomHandler) }
    updateMarkers()
    setTimeout(() => { if (map) map.invalidateSize() }, 150)
}

watch(() => props.problems, () => updateMarkers(), { deep: true })

onMounted(() => {
    loadLeaflet().then(() => nextTick(() => setTimeout(initMap, 150)))
})

onUnmounted(() => {
    if (removeZoomListener) {
        removeZoomListener()
        removeZoomListener = null
    }
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
    background: var(--lp-steps-bg);
}

.landing-map-container {
    width: 100%;
    height: 417px;
}

.landing-map-container :deep(.leaflet-marker-icon.landing-map-marker) {
    background: transparent !important;
    border: none !important;
}

.landing-map-container :deep(.leaflet-marker-icon.landing-map-marker--cluster) {
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
