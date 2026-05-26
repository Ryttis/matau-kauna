import { watch, onMounted, onUnmounted, nextTick, isRef, unref } from 'vue'
import { getMarkerColorForStatus, PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, MAP_MAX_ZOOM } from '@/utils/leafletKaunas'
import { MAP_HIGHLIGHT_COLOR, MAP_MERGED_MARKER_COLOR, MAP_CLUSTER_COLOR } from '@/constants/ui/theme'
import { clusterProblems } from '@/utils/mapClustering'
import { truncateMapPopupText } from '@/utils/mapPopupText'

const HIGHLIGHTED_MARKER_SIZE = 18
const MERGED_MARKER_SIZE = 14
const CLUSTER_MARKER_SIZE = 28
const DEFAULT_MARKER_SIZE = 14

const REFIT_DEBOUNCE_MS = 120
const EXTRA_REFIT_DELAYS_MS = [400, 800]

function escapeHtml(text) {
    return (text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function mapPopupDateLabel(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    return d.toLocaleDateString('lt-LT')
}

function mapPopupStatusLabel(problem) {
    if (problem?.reporter_status_label) return problem.reporter_status_label
    const status = problem?.reporter_status || problem?.status
    if (!status) return '-'
    return PROBLEM_STATUS_LABELS[status] || String(status)
}

export function useKaunasMap(containerRef, problemsToShowRef, options = {}) {
    const {
        onProblemOpen,
        onProblemMark = null,
        markerClassName = 'kaunas-map-marker',
        invalidateSizeDelay = 150,
        highlightedProblemId = null,
        mergedProblemIds = null,
        markableProblemIds = null,
        selectedProblemIds = null,
        autoFitView = true,
        focusHighlightedMarker = true,
    } = options

    const shouldAutoFitView = () => autoFitView == null || unref(autoFitView) !== false
    const shouldFocusHighlightedMarker = () =>
        focusHighlightedMarker == null || unref(focusHighlightedMarker) !== false

    let map = null
    let markersLayer = null
    let initialized = false
    let removeNavListener = null
    let resizeObserver = null
    let resizeRefitTimer = null
    let removeZoomListener = null
    const markersById = {}

    const getMergedSet = () => {
        const raw = unref(mergedProblemIds)
        return new Set((Array.isArray(raw) ? raw : []).map(Number))
    }
    const getMarkableSet = () => {
        const raw = unref(markableProblemIds)
        return new Set((Array.isArray(raw) ? raw : []).map(Number))
    }
    const getSelectedSet = () => {
        const raw = unref(selectedProblemIds)
        return new Set((Array.isArray(raw) ? raw : []).map(Number))
    }
    const getHighlightedId = () => unref(highlightedProblemId)

    function fitMapToProblems() {
        if (!map || typeof L === 'undefined' || !shouldAutoFitView()) return
        const list = Array.isArray(problemsToShowRef.value) ? problemsToShowRef.value : []
        if (list.length === 0) return
        if (list.length === 1) {
            const p = list[0]
            if (p.lat == null || p.lng == null) return
            map.setView([Number(p.lat), Number(p.lng)], 14)
            return
        }
        const allLatLng = list
            .filter((p) => p.lat != null && p.lng != null)
            .map((p) => [Number(p.lat), Number(p.lng)])
        if (allLatLng.length === 0) return
        const bounds = L.latLngBounds(allLatLng)
        if (!bounds.isValid()) return
        try {
            map.fitBounds(bounds.pad(0.1))
        } catch {}
    }

    function panToHighlightedMarker() {
        if (!map || !shouldFocusHighlightedMarker()) return
        const id = getHighlightedId()
        if (id == null) return
        const marker = markersById[id]
        if (marker) map.panTo(marker.getLatLng())
    }

    function scheduleInvalidateSize(refitAfter = false) {
        if (!map) return
        requestAnimationFrame(() => {
            if (!map) return
            map.invalidateSize({ animate: false })
            if (refitAfter) {
                fitMapToProblems()
                panToHighlightedMarker()
            }
        })
    }

    function buildPopupContent(problem) {
        const num = escapeHtml(problem.internal_number)
        const cat = escapeHtml((problem.category || '').trim() || '-')
        const addr = escapeHtml(truncateMapPopupText(problem.address) || '-')
        const statusText = escapeHtml(mapPopupStatusLabel(problem))
        const dateText = escapeHtml(mapPopupDateLabel(problem.created_at))
        const desc = escapeHtml(
            truncateMapPopupText(problem.description_preview || problem.description) || '-',
        )
        const id = Number(problem.id)
        const canMark = typeof onProblemMark === 'function' && id && getMarkableSet().has(id)
        const isSelected = canMark && getSelectedSet().has(id)
        const markBtnClass = `kaunas-map-popup-mark-problem kaunas-map-popup-view-btn${isSelected ? ' is-selected' : ''}`
        const markBtnLabel = isSelected ? 'Pažymėta' : 'Pažymėti'
        const markBtn = canMark
            ? `<button type="button" class="${markBtnClass}" data-mark-problem-id="${id}">${markBtnLabel}</button>`
            : ''
        const viewBtn = onProblemOpen && id
            ? `<p class="kaunas-map-popup-actions"><button type="button" class="kaunas-map-popup-open-problem kaunas-map-popup-view-btn" data-open-problem-id="${id}">Peržiūrėti</button>${markBtn}</p>`
            : ''
        const dateRow = dateText
            ? `<div class="kaunas-map-popup-row"><span class="kaunas-map-popup-k">Data</span> ${dateText}</div>`
            : ''
        const meta = `<div class="kaunas-map-popup-meta">
            <div class="kaunas-map-popup-row"><span class="kaunas-map-popup-k">Kategorija</span> ${cat}</div>
            <div class="kaunas-map-popup-row"><span class="kaunas-map-popup-k">Adresas</span> ${addr}</div>
            <div class="kaunas-map-popup-row"><span class="kaunas-map-popup-k">Būsena</span> ${statusText}</div>
            ${dateRow}
        </div>`
        return `<div class="kaunas-map-popup-body"><strong class="kaunas-map-popup-title">${num}</strong>${meta}<p class="kaunas-map-popup-desc">${desc}</p>${viewBtn}</div>`
    }

    function buildClusterPopupContent(problems) {
        const markableSet = getMarkableSet()
        const selectedSet = getSelectedSet()
        const canMarkAny = typeof onProblemMark === 'function'
        const items = (problems || []).slice(0, 15).map((problem) => {
            const num = escapeHtml(problem.internal_number)
            const catRaw = (problem.category || '').trim()
            const cat = catRaw ? escapeHtml(catRaw) : ''
            const previewRaw = (problem.description_preview || problem.description || '').trim()
            const preview = escapeHtml(previewRaw)
            const id = Number(problem.id)
            const restBits = [cat, preview].filter(Boolean).join(' · ')
            const canMark = canMarkAny && id && markableSet.has(id)
            const isSelected = canMark && selectedSet.has(id)
            const markBtnClass = `kaunas-map-popup-mark-problem kaunas-map-popup-view-btn kaunas-map-popup-view-btn--sm${isSelected ? ' is-selected' : ''}`
            const markBtnLabel = isSelected ? 'Pažymėta' : 'Pažymėti'
            const markBtn = canMark
                ? `<button type="button" class="${markBtnClass}" data-mark-problem-id="${id}">${markBtnLabel}</button>`
                : ''
            return `<div class="kaunas-map-popup-cluster-item"><a href="#" class="kaunas-map-popup-open-problem" data-open-problem-id="${id}"><span class="kaunas-map-popup-cluster-line-num">${num}</span><span class="kaunas-map-popup-cluster-line-rest">${restBits}</span></a>${markBtn}</div>`
        })
        const rest = problems.length > 15 ? problems.length - 15 : 0
        const restLine = rest > 0 ? `<div class="kaunas-map-popup-more-wrap"><span class="kaunas-map-popup-more">+${rest}</span></div>` : ''
        return `<div class="kaunas-map-popup-cluster">${items.join('')}${restLine}</div>`
    }

    function setupPopupNavListener() {
        if (!map) return
        const handler = (event) => {
            const markBtn = event.target.closest('[data-mark-problem-id]')
            if (markBtn) {
                event.preventDefault()
                event.stopPropagation()
                const id = Number(markBtn.getAttribute('data-mark-problem-id'))
                if (typeof onProblemMark === 'function' && id) {
                    onProblemMark(id)
                }
                const willBeSelected = !markBtn.classList.contains('is-selected')
                markBtn.classList.toggle('is-selected', willBeSelected)
                markBtn.textContent = willBeSelected ? 'Pažymėta' : 'Pažymėti'
                return
            }
            const openBtn = event.target.closest('.kaunas-map-popup-open-problem[data-open-problem-id]')
            if (!openBtn) return
            event.preventDefault()
            event.stopPropagation()
            const id = Number(openBtn.getAttribute('data-open-problem-id'))
            if (onProblemOpen && id) onProblemOpen(id)
            map.closePopup()
        }
        const container = map.getContainer()
        container.addEventListener('click', handler)
        return () => container.removeEventListener('click', handler)
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
                const problem = item.problem
                const isMain = mainId != null && Number(problem.id) === Number(mainId)
                const isMerged = merged.has(Number(problem.id))
                const color = (isMain || isMerged)
                    ? (isMain ? MAP_HIGHLIGHT_COLOR : MAP_MERGED_MARKER_COLOR)
                    : getMarkerColorForStatus(problem.reporter_status || problem.status)
                const size = isMain ? HIGHLIGHTED_MARKER_SIZE : (isMerged ? MERGED_MARKER_SIZE : DEFAULT_MARKER_SIZE)
                const icon = L.divIcon({
                    className: markerClassName,
                    html: `<span style="background-color:${color};width:${size}px;height:${size}px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
                    iconSize: [size, size],
                    iconAnchor: [size / 2, size / 2],
                })
                const marker = L.marker([problem.lat, problem.lng], { icon })
                    .bindPopup(buildPopupContent(problem), { maxWidth: 320, className: 'kaunas-map-popup-root' })
                    .addTo(markersLayer)
                markersById[problem.id] = marker
                if (onProblemOpen) marker.on('dblclick', () => onProblemOpen(problem.id))
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
                    .bindPopup(buildClusterPopupContent(problems), { maxWidth: 380, className: 'kaunas-map-popup-root' })
                    .addTo(markersLayer)
            }
        })
        if (!skipFitView && shouldAutoFitView()) {
            fitMapToProblems()
        }
        nextTick(() => panToHighlightedMarker())
    }

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
        removeZoomListener = () => {
            if (map) map.off('zoomend', zoomHandler)
        }
        updateMarkers()
        setTimeout(() => scheduleInvalidateSize(true), invalidateSizeDelay)
        EXTRA_REFIT_DELAYS_MS.forEach((ms) => setTimeout(() => scheduleInvalidateSize(true), ms))

        if (typeof ResizeObserver !== 'undefined' && containerRef.value) {
            resizeObserver = new ResizeObserver(() => {
                if (resizeRefitTimer) clearTimeout(resizeRefitTimer)
                resizeRefitTimer = setTimeout(() => {
                    resizeRefitTimer = null
                    scheduleInvalidateSize(shouldAutoFitView())
                }, REFIT_DEBOUNCE_MS)
            })
            resizeObserver.observe(containerRef.value)
        }
    }

    function cleanup() {
        if (resizeRefitTimer) {
            clearTimeout(resizeRefitTimer)
            resizeRefitTimer = null
        }
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
        if (!isRef(option)) return
        watch(option, () => updateMarkers(true), deep ? { deep: true } : undefined)
    }

    watchOption(highlightedProblemId)
    watchOption(autoFitView)
    watchOption(focusHighlightedMarker)
    watchOption(mergedProblemIds, true)
    watchOption(markableProblemIds, true)

    function syncOpenPopupSelection() {
        if (!map) return
        const selectedSet = getSelectedSet()
        const buttons = map.getContainer().querySelectorAll('[data-mark-problem-id]')
        buttons.forEach((btn) => {
            const id = Number(btn.getAttribute('data-mark-problem-id'))
            const shouldBeSelected = selectedSet.has(id)
            const isSelected = btn.classList.contains('is-selected')
            if (shouldBeSelected === isSelected) return
            btn.classList.toggle('is-selected', shouldBeSelected)
            btn.textContent = shouldBeSelected ? 'Pažymėta' : 'Pažymėti'
        })
    }

    if (isRef(selectedProblemIds)) {
        watch(selectedProblemIds, syncOpenPopupSelection, { deep: true })
    }

    onUnmounted(cleanup)
}
