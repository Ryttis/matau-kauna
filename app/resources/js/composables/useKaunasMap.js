import { watch, onMounted, onUnmounted, nextTick } from 'vue'
import { getMarkerColorForStatus } from '@/constants/problemStatus'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/composables/leafletKaunas'

const HIGHLIGHTED_MARKER_COLOR = '#1946A0'
const HIGHLIGHTED_MARKER_SIZE = 18
const MERGED_MARKER_COLOR = '#1946A0'
const MERGED_MARKER_SIZE = 14

export function useKaunasMap(containerRef, problemsToShowRef, options = {}) {
    const { onProblemOpen, markerClassName = 'kaunas-map-marker', invalidateSizeDelay = 150, highlightedProblemId = null, mergedProblemIds = null } = options
    let map = null
    let markersLayer = null
    let initialized = false

    const getMergedSet = () => {
        const raw = mergedProblemIds && typeof mergedProblemIds === 'object' && 'value' in mergedProblemIds ? mergedProblemIds.value : mergedProblemIds
        return new Set(Array.isArray(raw) ? raw : [])
    }
    const getHighlightedId = () => (highlightedProblemId && typeof highlightedProblemId === 'object' && 'value' in highlightedProblemId ? highlightedProblemId.value : highlightedProblemId)

    function updateMarkers() {
        if (!map || !markersLayer || typeof L === 'undefined') return
        const list = Array.isArray(problemsToShowRef.value) ? problemsToShowRef.value : []
        const merged = getMergedSet()
        const mainId = getHighlightedId()
        markersLayer.clearLayers()
        list.forEach((p) => {
            const isMain = mainId != null && p.id === mainId
            const isMerged = merged.has(p.id)
            const color = (isMain || isMerged) ? (isMain ? HIGHLIGHTED_MARKER_COLOR : MERGED_MARKER_COLOR) : getMarkerColorForStatus(p.status)
            const size = isMain ? HIGHLIGHTED_MARKER_SIZE : (isMerged ? MERGED_MARKER_SIZE : 14)
            const icon = L.divIcon({
                className: markerClassName,
                html: `<span style="background-color:${color};width:${size}px;height:${size}px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
            })
            const marker = L.marker([p.lat, p.lng], { icon })
                .bindPopup(`<strong>${p.internal_number || ''}</strong><br>${p.title || ''}`)
                .addTo(markersLayer)
            if (onProblemOpen) {
                marker.on('dblclick', () => onProblemOpen(p.id))
            }
        })
        if (list.length === 1) {
            map.setView([list[0].lat, list[0].lng], 14)
        } else if (list.length > 1) {
            const bounds = L.latLngBounds(list.map((p) => [p.lat, p.lng]))
            map.fitBounds(bounds.pad(0.1))
        }
    }

    function initMap() {
        if (initialized || !containerRef.value || typeof L === 'undefined') return
        map = createBaseMap(containerRef.value, { maxZoom: MAP_MAX_ZOOM })
        if (!map) return
        map.setView(KAUNAS_CENTER, 12)
        markersLayer = L.layerGroup().addTo(map)
        initialized = true
        updateMarkers()
        setTimeout(() => { if (map) map.invalidateSize() }, invalidateSizeDelay)
    }

    function cleanup() {
        if (map) {
            map.remove()
            map = null
            markersLayer = null
            initialized = false
        }
    }

    onMounted(() => {
        loadLeaflet().then(() => nextTick(() => setTimeout(initMap, 100)))
    })

    watch(problemsToShowRef, () => updateMarkers(), { deep: true })
    if (highlightedProblemId && typeof highlightedProblemId === 'object' && 'value' in highlightedProblemId) {
        watch(highlightedProblemId, () => updateMarkers())
    }
    if (mergedProblemIds && typeof mergedProblemIds === 'object' && 'value' in mergedProblemIds) {
        watch(mergedProblemIds, () => updateMarkers(), { deep: true })
    }

    onUnmounted(cleanup)

    return { updateMarkers, initMap, cleanup }
}
