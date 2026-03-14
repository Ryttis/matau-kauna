import { watch, onMounted, onUnmounted, nextTick } from 'vue'
import { getMarkerColorForStatus } from '@/constants/problemStatus'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/composables/leafletKaunas'
import { ICON_LEFT_ARROW, ICON_RIGHT_ARROW } from '@/constants/icons'
import { MAP_HIGHLIGHT_COLOR, MAP_MERGED_MARKER_COLOR, MAP_CLUSTER_COLOR } from '@/constants/theme'
import { clusterProblems } from '@/utils/mapClustering'

const HIGHLIGHTED_MARKER_SIZE = 18
const MERGED_MARKER_SIZE = 14
const CLUSTER_MARKER_SIZE = 28
const DEFAULT_MARKER_SIZE = 14

function unwrapRef(maybeRef) {
    if (maybeRef != null && typeof maybeRef === 'object' && 'value' in maybeRef) return maybeRef.value
    return maybeRef
}

function escapeHtml(s) {
    return (s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function navButton(problemId, icon, title, disabled) {
    const cls = 'kaunas-map-popup-nav-btn'
    const iconCls = 'kaunas-map-popup-nav-icon'
    const src = icon === 'prev' ? ICON_LEFT_ARROW : ICON_RIGHT_ARROW
    if (disabled) {
        return `<span class="${cls} ${cls}--disabled" title="${title}"><img src="${src}" alt="" class="${iconCls}" /></span>`
    }
    return `<button type="button" class="${cls}" data-nav-problem-id="${problemId}" title="${title}"><img src="${src}" alt="${title}" class="${iconCls}" /></button>`
}

export function useKaunasMap(containerRef, problemsToShowRef, options = {}) {
    const { onProblemOpen, markerClassName = 'kaunas-map-marker', invalidateSizeDelay = 150, highlightedProblemId = null, mergedProblemIds = null, navigableGroupsRef = null } = options
    let map = null
    let markersLayer = null
    let initialized = false
    let removeNavListener = null
    const markersById = {}

    const getMergedSet = () => {
        const raw = unwrapRef(mergedProblemIds)
        return new Set((Array.isArray(raw) ? raw : []).map(Number))
    }
    const getHighlightedId = () => unwrapRef(highlightedProblemId)
    const getNavigableGroups = () => {
        const raw = unwrapRef(navigableGroupsRef)
        if (!Array.isArray(raw)) return []
        return raw.filter((g) => Array.isArray(g) && g.length > 0)
    }

    function findGroupAndIndex(groups, pid) {
        const nid = Number(pid)
        for (const group of groups) {
            const idx = group.findIndex((n) => Number(n.id) === nid)
            if (idx >= 0) return { group, idx }
        }
        return { group: [], idx: -1 }
    }

    function buildPopupContent(p) {
        const num = escapeHtml(p.internal_number)
        const title = escapeHtml(p.title)
        const groups = getNavigableGroups()
        const { group: navList, idx } = findGroupAndIndex(groups, p.id)
        let nav = ''
        if (navList.length > 0 && idx >= 0) {
            const hasPrev = idx > 0
            const hasNext = idx < navList.length - 1
            const prevId = hasPrev ? navList[idx - 1].id : null
            const nextId = hasNext ? navList[idx + 1].id : null
            const prevBtn = navButton(prevId, 'prev', 'Ankstesnė', !hasPrev)
            const nextBtn = navButton(nextId, 'next', 'Kita', !hasNext)
            nav = `<div class="kaunas-map-popup-nav">${prevBtn}${nextBtn}</div>`
        }
        return `<strong>${num}</strong><br>${title}${nav}`
    }

    function buildClusterPopupContent(problems) {
        const lines = (problems || []).slice(0, 15).map((p) => {
            const num = escapeHtml(p.internal_number)
            const title = escapeHtml((p.title || '').slice(0, 40))
            return `<a href="#" class="kaunas-map-popup-open-problem" data-open-problem-id="${Number(p.id)}">${num} ${title}</a>`
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
            const btn = e.target.closest('.kaunas-map-popup-nav-btn[data-nav-problem-id]')
            if (!btn) return
            e.preventDefault()
            e.stopPropagation()
            const id = Number(btn.getAttribute('data-nav-problem-id'))
            const marker = markersById[id]
            if (!marker) return
            map.closePopup()
            map.panTo(marker.getLatLng())
            marker.openPopup()
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
        if (!skipFitView) {
            if (list.length === 1) {
                map.setView([list[0].lat, list[0].lng], 14)
            } else if (list.length > 1 && items.length > 0) {
                const allLatLng = list.map((p) => [p.lat, p.lng])
                const bounds = L.latLngBounds(allLatLng)
                map.fitBounds(bounds.pad(0.1))
            }
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
        setTimeout(() => { if (map) map.invalidateSize() }, invalidateSizeDelay)
    }

    function cleanup() {
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
    if (highlightedProblemId && typeof highlightedProblemId === 'object' && 'value' in highlightedProblemId) {
        watch(highlightedProblemId, updateMarkers)
    }
    if (mergedProblemIds && typeof mergedProblemIds === 'object' && 'value' in mergedProblemIds) {
        watch(mergedProblemIds, updateMarkers, { deep: true })
    }
    if (navigableGroupsRef && typeof navigableGroupsRef === 'object' && 'value' in navigableGroupsRef) {
        watch(navigableGroupsRef, updateMarkers, { deep: true })
    }

    onUnmounted(cleanup)

    return { updateMarkers, initMap, cleanup }
}
