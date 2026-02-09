import { watch, onMounted, onUnmounted, nextTick } from 'vue'
import { getMarkerColorForStatus } from '@/constants/problemStatus'

const KAUNAS_CENTER = [54.8985, 23.9036]
const KAUNAS_BOUNDS = [[54.75, 23.65], [55.05, 24.15]]

let leafletScriptLoaded = false
let leafletLoadPromise = null

function loadLeaflet() {
    if (leafletScriptLoaded) return Promise.resolve()
    if (leafletLoadPromise) return leafletLoadPromise
    leafletLoadPromise = new Promise((resolve) => {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
        link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
        link.crossOrigin = ''
        document.head.appendChild(link)
        const script = document.createElement('script')
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
        script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='
        script.crossOrigin = ''
        script.onload = () => {
            leafletScriptLoaded = true
            resolve()
        }
        document.head.appendChild(script)
    })
    return leafletLoadPromise
}

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
        map = L.map(containerRef.value, {
            center: KAUNAS_CENTER,
            zoom: 12,
            maxBounds: KAUNAS_BOUNDS,
            maxBoundsViscosity: 1.0,
            zoomControl: false,
        })
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(map)
        map.setMaxBounds(KAUNAS_BOUNDS)
        map.setMinZoom(11)
        map.setMaxZoom(14)
        map.setView(KAUNAS_CENTER, 12)
        L.control.zoom({ position: 'topright' }).addTo(map)
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
