import { watch, onMounted, onUnmounted, nextTick } from 'vue'
import { getMarkerColorForStatus } from '@/constants/problemStatus'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER } from '@/composables/leafletKaunas'

export function useKaunasMap(containerRef, problemsToShowRef, options = {}) {
    const { onProblemOpen, markerClassName = 'kaunas-map-marker', invalidateSizeDelay = 150 } = options
    let map = null
    let markersLayer = null
    let initialized = false

    function updateMarkers() {
        if (!map || !markersLayer || typeof L === 'undefined') return
        const list = Array.isArray(problemsToShowRef.value) ? problemsToShowRef.value : []
        markersLayer.clearLayers()
        list.forEach((p) => {
            const color = getMarkerColorForStatus(p.status)
            const icon = L.divIcon({
                className: markerClassName,
                html: `<span style="background-color:${color};width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7],
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
        map = createBaseMap(containerRef.value, { maxZoom: 14 })
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

    onUnmounted(cleanup)

    return { updateMarkers, initMap, cleanup }
}
