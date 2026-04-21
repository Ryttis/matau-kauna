<template>
    <section class="landing-map">
        <div ref="mapContainer" class="landing-map-container" />
        <div class="landing-map-legend">
            <span
                v-for="st in PUBLIC_MAP_LEGEND_STATUS_ORDER"
                :key="st"
                class="landing-map-legend-item"
            >
                <span
                    class="landing-map-legend-dot"
                    :style="{ background: MARKER_COLOR_BY_STATUS[st] || '#95a5a6' }"
                />
                {{ PROBLEM_STATUS_LABELS[st] }}
            </span>
        </div>
    </section>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/utils/leafletKaunas'
import {
    getMarkerColorForStatus,
    MARKER_COLOR_BY_STATUS,
    PROBLEM_STATUS_LABELS,
    PUBLIC_MAP_LEGEND_STATUS_ORDER,
} from '@/constants/domain/problemStatus'
import { MAP_CLUSTER_COLOR } from '@/constants/ui/theme'
import { clusterProblems } from '@/utils/mapClustering'
import { truncateMapPopupText } from '@/utils/mapPopupText'

const CLUSTER_SIZE = 28

function escapeHtml(s) {
    return (s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function landingMapDateLabel(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    return d.toLocaleDateString('lt-LT')
}

function landingMapStatusLabel(status) {
    if (!status) return '-'
    return PROBLEM_STATUS_LABELS[status] || String(status)
}

function singleProblemPopupHtml(p) {
    const id = Number(p.id)
    const num = escapeHtml(p.internal_number || '')
    const cat = escapeHtml((p.category || '').trim() || '-')
    const addr = escapeHtml(truncateMapPopupText(p.address) || '-')
    const statusText = escapeHtml(landingMapStatusLabel(p.status))
    const dateText = escapeHtml(landingMapDateLabel(p.created_at))
    const desc = escapeHtml(
        truncateMapPopupText(p.description_preview || p.description) || '-',
    )
    const dateRow = dateText
        ? `<div class="landing-map-popup-row"><span class="landing-map-popup-k">Data</span> ${dateText}</div>`
        : ''
    const meta = `<div class="landing-map-popup-meta">
        <div class="landing-map-popup-row"><span class="landing-map-popup-k">Kategorija</span> ${cat}</div>
        <div class="landing-map-popup-row"><span class="landing-map-popup-k">Adresas</span> ${addr}</div>
        <div class="landing-map-popup-row"><span class="landing-map-popup-k">Būsena</span> ${statusText}</div>
        ${dateRow}
    </div>`
    return `<div class="landing-map-popup-body"><strong class="landing-map-popup-title">${num}</strong>${meta}<p class="landing-map-popup-desc">${desc}</p><p class="landing-map-popup-actions"><a class="landing-map-popup-view" href="/reports/${id}">Peržiūrėti</a></p></div>`
}

function clusterPopupContent(problems) {
    const lines = (problems || []).slice(0, 12).map((p) => {
        const id = Number(p.id)
        const num = escapeHtml(p.internal_number || '')
        const catRaw = (p.category || '').trim()
        const cat = catRaw ? `${escapeHtml(catRaw)} · ` : ''
        const preview = escapeHtml((p.description_preview || p.description || '').slice(0, 36))
        const href = `/reports/${id}`
        return `<div class="landing-map-cluster-row"><span><span class="landing-map-cluster-num">${num}</span> <span class="landing-map-cluster-rest">${cat}${preview}</span></span><a class="landing-map-popup-view" href="${href}">Peržiūrėti</a></div>`
    })
    const rest = problems.length > 12 ? problems.length - 12 : 0
    const restLine = rest > 0 ? `<div class="landing-map-cluster-more">+${rest}</div>` : ''
    return lines.join('') + restLine
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
                .bindPopup(singleProblemPopupHtml(p), { maxWidth: 320, className: 'landing-map-popup-root' })
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
                .bindPopup(clusterPopupContent(clusterProblemsList), { maxWidth: 320, className: 'landing-map-popup-root' })
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

.landing-map-container :deep(.landing-map-popup-body) {
    min-width: 0;
}

.landing-map-container :deep(.landing-map-popup-title) {
    display: block;
    font-size: 1rem;
    margin-bottom: 6px;
}

.landing-map-container :deep(.landing-map-popup-meta) {
    font-size: 12px;
    line-height: 1.4;
    color: #555;
    margin: 0 0 8px;
}

.landing-map-container :deep(.landing-map-popup-row) {
    margin: 2px 0;
}

.landing-map-container :deep(.landing-map-popup-k) {
    display: inline-block;
    min-width: 5.5rem;
    color: #666;
    font-weight: 600;
}

.landing-map-container :deep(.landing-map-popup-desc) {
    margin: 0;
    font-size: 13px;
    line-height: 1.45;
    color: #222;
}

.landing-map-container :deep(.landing-map-popup-actions) {
    margin: 10px 0 0;
    text-align: left;
}

.landing-map-container :deep(.landing-map-cluster-num) {
    font-weight: 600;
}

.landing-map-container :deep(.landing-map-cluster-rest) {
    font-size: 12px;
    color: #555;
}

.landing-map-container :deep(.landing-map-popup-view) {
    display: inline-block;
    padding: 5px 12px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--lp-primary, #1946a0);
    border-radius: 6px;
    background: #fff;
    color: var(--lp-primary, #1946a0);
}

.landing-map-container :deep(.landing-map-popup-view:hover) {
    opacity: 0.9;
}

.landing-map-container :deep(.landing-map-cluster-row) {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 6px 10px;
    margin-bottom: 8px;
}

.landing-map-container :deep(.landing-map-cluster-row:last-of-type) {
    margin-bottom: 0;
}

.landing-map-container :deep(.landing-map-cluster-more) {
    margin-top: 6px;
    font-size: 0.875rem;
    color: #666;
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
</style>
