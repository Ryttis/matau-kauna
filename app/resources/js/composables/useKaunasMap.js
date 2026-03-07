import { watch, onMounted, onUnmounted, nextTick } from 'vue'
import { getMarkerColorForStatus } from '@/constants/problemStatus'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/composables/leafletKaunas'
import { ICON_LEFT_ARROW, ICON_RIGHT_ARROW } from '@/constants/icons'
import { MAP_HIGHLIGHT_COLOR, MAP_MERGED_MARKER_COLOR } from '@/constants/theme'

const HIGHLIGHTED_MARKER_COLOR = MAP_HIGHLIGHT_COLOR
const HIGHLIGHTED_MARKER_SIZE = 18
const MERGED_MARKER_COLOR = MAP_MERGED_MARKER_COLOR
const MERGED_MARKER_SIZE = 14

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

    function setupPopupNavListener() {
        if (!map) return
        const handler = (e) => {
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

    function updateMarkers() {
        if (!map || !markersLayer || typeof L === 'undefined') return
        const list = Array.isArray(problemsToShowRef.value) ? problemsToShowRef.value : []
        const merged = getMergedSet()
        const mainId = getHighlightedId()
        Object.keys(markersById).forEach((k) => delete markersById[k])
        markersLayer.clearLayers()
        list.forEach((p) => {
            const isMain = mainId != null && Number(p.id) === Number(mainId)
            const isMerged = merged.has(Number(p.id))
            const color = (isMain || isMerged) ? (isMain ? HIGHLIGHTED_MARKER_COLOR : MERGED_MARKER_COLOR) : getMarkerColorForStatus(p.status)
            const size = isMain ? HIGHLIGHTED_MARKER_SIZE : (isMerged ? MERGED_MARKER_SIZE : 14)
            const icon = L.divIcon({
                className: markerClassName,
                html: `<span style="background-color:${color};width:${size}px;height:${size}px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
            })
            const popupContent = buildPopupContent(p)
            const marker = L.marker([p.lat, p.lng], { icon })
                .bindPopup(popupContent)
                .addTo(markersLayer)
            markersById[p.id] = marker
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
        removeNavListener = setupPopupNavListener()
        updateMarkers()
        setTimeout(() => { if (map) map.invalidateSize() }, invalidateSizeDelay)
    }

    function cleanup() {
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
