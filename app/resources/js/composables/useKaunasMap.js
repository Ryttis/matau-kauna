import { watch, onMounted, onUnmounted, nextTick, isRef, unref } from 'vue'
import { getMarkerColorForStatus } from '@/constants/problemStatus'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/composables/leafletKaunas'
import { MAP_HIGHLIGHT_COLOR, MAP_MERGED_MARKER_COLOR, MAP_CLUSTER_COLOR } from '@/constants/theme'
import { clusterProblems } from '@/utils/mapClustering'

const HIGHLIGHTED_MARKER_SIZE = 18
const MERGED_MARKER_SIZE = 14
const CLUSTER_MARKER_SIZE = 28
const DEFAULT_MARKER_SIZE = 14

function escapeHtml(s) {
    return (s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

export function useKaunasMap(containerRef, problemsToShowRef, options = {}) {
    const {
        onProblemOpen,
        markerClassName = 'kaunas-map-marker',
        invalidateSizeDelay = 150,
        highlightedProblemId = null,
        mergedProblemIds = null,
        autoFitView = true,
        focusHighlightedMarker = true,
    } = options

    const shouldAutoFitView = () => {
        const v = autoFitView
        if (v == null) return true
        return unref(v) !== false
    }

    const shouldFocusHighlightedMarker = () => {
        const v = focusHighlightedMarker
        if (v == null) return true
        return unref(v) !== false
    }
    let map = null
    let markersLayer = null
    let initialized = false
    let removeNavListener = null
    let resizeObserver = null
    const markersById = {}

    function scheduleInvalidateSize() {
        if (!map) return
        requestAnimationFrame(() => {
            if (map) map.invalidateSize({ animate: false })
        })
    }

    const getMergedSet = () => {
        const raw = unref(mergedProblemIds)
        return new Set((Array.isArray(raw) ? raw : []).map(Number))
    }
    const getHighlightedId = () => unref(highlightedProblemId)

    function buildPopupContent(p) {
        const num = escapeHtml(p.internal_number)
        const preview = (p.description_preview || p.description || '').slice(0, 200)
        const line = escapeHtml(preview)
        return `<strong>${num}</strong><br>${line}`
    }

    function buildClusterPopupContent(problems) {
        const lines = (problems || []).slice(0, 15).map((p) => {
            const num = escapeHtml(p.internal_number)
            const preview = escapeHtml((p.description_preview || p.description || '').slice(0, 40))
            return `<a href="#" class="kaunas-map-popup-open-problem" data-open-problem-id="${Number(p.id)}">${num} ${preview}</a>`
        })
        const rest = problems.length > 15 ? problems.length - 15 : 0
        const restLine = rest > 0 ? `<span class="kaunas-map-popup-more">+${rest}</span>` : ''
        return `<div class="kaunas-map-popup-cluster">${lines.join('<br>')}${restLine}</div>`
    }

    function setupPopupNavListener() {
        if (!map) return
        const handler = (e) => {
            const openBtn = e.target.closest('.kaunas-map-popup-open-problem[data-open-problem-id]')
            if (openBtn) {
                e.preventDefault()
                e.stopPropagation()
                const id = Number(openBtn.getAttribute('data-open-problem-id'))
                if (onProblemOpen && id) onProblemOpen(id)
                map.closePopup()
                return
            }
        }
        const container = map.getContainer()
        container.addEventListener('click', handler)
        const cleanupNav = () => container.removeEventListener('click', handler)
        return cleanupNav
    }

    function updateMarkers(skipFitView = false) {
        if (!map || !markersLayer || typeof L === 'undefined') return
        const list = Array.isArray(problemsToShowRef.value) ? problemsToShowRef.value : []
        const zoom = map.getZoom()
        const items = clusterProblems(list, zoom)
        const merged = getMergedSet()
        const mainId = getHighlightedId()
        Object.keys(markersById).forEach((k) => delete markersById[k])
        markersLayer.clearLayers()
        items.forEach((item) => {
            if (item.type === 'single') {
                const p = item.problem
                const isMain = mainId != null && Number(p.id) === Number(mainId)
                const isMerged = merged.has(Number(p.id))
                const color = (isMain || isMerged) ? (isMain ? MAP_HIGHLIGHT_COLOR : MAP_MERGED_MARKER_COLOR) : getMarkerColorForStatus(p.status)
                const size = isMain ? HIGHLIGHTED_MARKER_SIZE : (isMerged ? MERGED_MARKER_SIZE : DEFAULT_MARKER_SIZE)
                const icon = L.divIcon({
                    className: markerClassName,
                    html: `<span style="background-color:${color};width:${size}px;height:${size}px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
                    iconSize: [size, size],
                    iconAnchor: [size / 2, size / 2],
                })
                const marker = L.marker([p.lat, p.lng], { icon })
                    .bindPopup(buildPopupContent(p))
                    .addTo(markersLayer)
                markersById[p.id] = marker
                if (onProblemOpen) marker.on('dblclick', () => onProblemOpen(p.id))
            } else {
                const { lat, lng, count, problems } = item
                const label = count >= 9 ? '+9' : `+${count}`
                const icon = L.divIcon({
                    className: `${markerClassName} ${markerClassName}--cluster`,
                    html: `<span style="background-color:${MAP_CLUSTER_COLOR};width:${CLUSTER_MARKER_SIZE}px;height:${CLUSTER_MARKER_SIZE}px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;box-sizing:border-box;">${label}</span>`,
                    iconSize: [CLUSTER_MARKER_SIZE, CLUSTER_MARKER_SIZE],
                    iconAnchor: [CLUSTER_MARKER_SIZE / 2, CLUSTER_MARKER_SIZE / 2],
                })
                L.marker([lat, lng], { icon })
                    .bindPopup(buildClusterPopupContent(problems))
                    .addTo(markersLayer)
            }
        })
        if (!skipFitView && shouldAutoFitView()) {
            if (list.length === 1) {
                map.setView([list[0].lat, list[0].lng], 14)
            } else if (list.length > 1 && items.length > 0) {
                const allLatLng = list.map((p) => [p.lat, p.lng])
                const bounds = L.latLngBounds(allLatLng)
                map.fitBounds(bounds.pad(0.1))
            }
        }
        const hiId = getHighlightedId()
        if (shouldFocusHighlightedMarker() && hiId && markersById[hiId]) {
            nextTick(() => {
                if (map && markersById[hiId]) {
                    map.panTo(markersById[hiId].getLatLng())
                }
            })
        }
    }

    let removeZoomListener = null

    function initMap() {
        if (initialized || !containerRef.value || typeof L === 'undefined') return
        map = createBaseMap(containerRef.value, { maxZoom: MAP_MAX_ZOOM })
        if (!map) return
        map.setView(KAUNAS_CENTER, 12)
        markersLayer = L.layerGroup().addTo(map)
        initialized = true
        removeNavListener = setupPopupNavListener()
        const zoomHandler = () => updateMarkers(true)
        map.on('zoomend', zoomHandler)
        removeZoomListener = () => { if (map) map.off('zoomend', zoomHandler) }
        updateMarkers()
        setTimeout(scheduleInvalidateSize, invalidateSizeDelay)
        setTimeout(scheduleInvalidateSize, 400)

        if (typeof ResizeObserver !== 'undefined' && containerRef.value) {
            resizeObserver = new ResizeObserver(() => scheduleInvalidateSize())
            resizeObserver.observe(containerRef.value)
        }
    }

    function cleanup() {
        if (resizeObserver) {
            resizeObserver.disconnect()
            resizeObserver = null
        }
        if (removeZoomListener) {
            removeZoomListener()
            removeZoomListener = null
        }
        if (removeNavListener) {
            removeNavListener()
            removeNavListener = null
        }
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

    watch(problemsToShowRef, updateMarkers, { deep: true })

    const watchOption = (option, deep = false) => {
        if (!isRef(option)) {
            return
        }
        watch(option, () => updateMarkers(true), deep ? { deep: true } : undefined)
    }

    watchOption(highlightedProblemId)
    watchOption(autoFitView)
    watchOption(focusHighlightedMarker)
    watchOption(mergedProblemIds, true)

    onUnmounted(cleanup)

    return { updateMarkers, initMap, cleanup }
}
