import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, KAUNAS_BOUNDS } from '@/utils/leafletKaunas'

const NOMINATIM_DELAY_MS = 1100
const NOMINATIM_HEADERS = { Accept: 'application/json', 'Accept-Language': 'lt' }
const MAX_MARKER_MOVES = 3
const MARKER_COOLDOWN_MS = 4000
const MARKER_LOCKED_OPACITY = 0.45
const OUTSIDE_CITY_MESSAGE = 'Registruoti galima tik Kauno miesto savivaldybės teritorijoje.'
let lastGeocodeAt = 0

let NOMINATIM_VIEWBOX = '23.65,55.05,24.15,54.75'
try {
    if (KAUNAS_BOUNDS?.[0] && KAUNAS_BOUNDS?.[1]) {
        const [[minLat, minLon], [maxLat, maxLon]] = KAUNAS_BOUNDS
        NOMINATIM_VIEWBOX = `${minLon},${maxLat},${maxLon},${minLat}`
    }
} catch {}

function isInKaunasBounds(lat, lng) {
    if (!KAUNAS_BOUNDS || KAUNAS_BOUNDS.length < 2) return true
    const [[minLat, minLon], [maxLat, maxLon]] = KAUNAS_BOUNDS
    return lat >= minLat && lat <= maxLat && lng >= minLon && lng <= maxLon
}

function isKaunasCityAddress(displayName) {
    return !String(displayName ?? '').toLowerCase().includes('rajono savivaldyb')
}

function rateLimitWait() {
    const wait = Math.max(0, NOMINATIM_DELAY_MS - (Date.now() - lastGeocodeAt))
    return wait ? new Promise((resolve) => setTimeout(resolve, wait)) : Promise.resolve()
}

function shortenQuery(raw, maxLen, fallbackLen) {
    const s = String(raw).trim()
    if (s.length <= maxLen) return s
    const shortened = s.slice(0, maxLen).replace(/,?\s*[^,]*$/, '') || s.slice(0, fallbackLen)
    return shortened
}

function parseNominatimItem(item) {
    return {
        display_name: item.display_name || '',
        lat: parseFloat(item.lat),
        lng: parseFloat(item.lon),
    }
}

async function nominatimSearch(params) {
    await rateLimitWait()
    lastGeocodeAt = Date.now()
    const url = new URL('https://nominatim.openstreetmap.org/search')
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value))
    const res = await fetch(url, { headers: NOMINATIM_HEADERS })
    const data = await res.json()
    return Array.isArray(data) ? data : []
}

async function geocodeAddress(query) {
    if (!query?.trim()) return null
    const shortQuery = shortenQuery(query, 80, 50)
    const data = await nominatimSearch({
        q: `${shortQuery}, Kaunas`,
        format: 'json',
        limit: 3,
        viewbox: NOMINATIM_VIEWBOX,
        bounded: 0,
    })
    if (!data.length) return null
    const first = data.find((row) => isInKaunasBounds(parseFloat(row.lat), parseFloat(row.lon))) || data[0]
    return {
        lat: parseFloat(first.lat),
        lng: parseFloat(first.lon),
        address: first.display_name || query,
    }
}

async function fetchAddressSuggestions(query) {
    const raw = String(query ?? '').trim()
    if (raw.length < 2) return []
    const shortQuery = shortenQuery(raw, 60, 40)
    const data = await nominatimSearch({
        q: `${shortQuery}, Kaunas`,
        format: 'json',
        limit: 12,
        addressdetails: 1,
        viewbox: NOMINATIM_VIEWBOX,
        bounded: 0,
    })
    return data
        .map(parseNominatimItem)
        .filter((suggestion) => isInKaunasBounds(suggestion.lat, suggestion.lng))
        .slice(0, 8)
}

async function reverseGeocode(lat, lng, signal) {
    await rateLimitWait()
    if (signal?.aborted) return ''
    lastGeocodeAt = Date.now()
    const res = await fetch(
        `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`,
        { headers: NOMINATIM_HEADERS, signal }
    )
    const data = await res.json()
    return data?.display_name ?? ''
}

export function useKaunasMapLocationPicker(containerRef, options = {}) {
    const {
        initialLat = KAUNAS_CENTER[0],
        initialLng = KAUNAS_CENTER[1],
        initialAddress = '',
        invalidateSizeDelay = 150,
    } = options
    const lat = ref(initialLat)
    const lng = ref(initialLng)
    const address = ref(typeof initialAddress === 'string' ? initialAddress : '')
    const isGeocoding = ref(false)
    const markerLocked = ref(false)
    const locationError = ref('')
    let map = null
    let marker = null
    let initialized = false
    let lastValidLat = initialLat
    let lastValidLng = initialLng
    let markerMoveCount = 0
    let markerCooldownTimer = null
    let reverseAbortController = null

    function lockMarker() {
        markerLocked.value = true
        if (marker?.dragging) marker.dragging.disable()
        if (marker?.setOpacity) marker.setOpacity(MARKER_LOCKED_OPACITY)
        if (markerCooldownTimer) clearTimeout(markerCooldownTimer)
        markerCooldownTimer = setTimeout(() => {
            markerLocked.value = false
            markerMoveCount = 0
            if (marker?.dragging) marker.dragging.enable()
            if (marker?.setOpacity) marker.setOpacity(1)
        }, MARKER_COOLDOWN_MS)
    }

    function registerMarkerMove() {
        markerMoveCount += 1
        if (markerMoveCount >= MAX_MARKER_MOVES) lockMarker()
    }

    function commitValidPosition(newLat, newLng) {
        lat.value = newLat
        lng.value = newLng
        lastValidLat = newLat
        lastValidLng = newLng
    }

    function revertToLastValid() {
        lat.value = lastValidLat
        lng.value = lastValidLng
        if (marker) marker.setLatLng([lastValidLat, lastValidLng])
    }

    async function updateAddressFromLatLng(newLat, newLng) {
        if (reverseAbortController) reverseAbortController.abort()
        reverseAbortController = new AbortController()
        const signal = reverseAbortController.signal
        try {
            const addr = await reverseGeocode(newLat, newLng, signal)
            if (signal.aborted) return
            if (addr && !isKaunasCityAddress(addr)) {
                locationError.value = OUTSIDE_CITY_MESSAGE
                revertToLastValid()
                return
            }
            locationError.value = ''
            lastValidLat = newLat
            lastValidLng = newLng
            if (addr) address.value = addr
        } catch {}
    }

    function setMarkerPosition(newLat, newLng) {
        lat.value = newLat
        lng.value = newLng
        if (marker && typeof L !== 'undefined') {
            marker.setLatLng([newLat, newLng])
            map.setView([newLat, newLng], map.getZoom())
        }
    }

    async function searchAddress(query) {
        isGeocoding.value = true
        try {
            const result = await geocodeAddress(query)
            if (result && isInKaunasBounds(result.lat, result.lng) && isKaunasCityAddress(result.address)) {
                locationError.value = ''
                commitValidPosition(result.lat, result.lng)
                address.value = result.address
                if (marker) {
                    marker.setLatLng([result.lat, result.lng])
                    map.setView([result.lat, result.lng], 14)
                }
            }
        } finally {
            isGeocoding.value = false
        }
    }

    function applySuggestionSelection(item) {
        const newLat = Number(item?.lat)
        const newLng = Number(item?.lng)
        if (!Number.isFinite(newLat) || !Number.isFinite(newLng)) {
            return
        }
        if (!isInKaunasBounds(newLat, newLng) || !isKaunasCityAddress(item.display_name)) {
            return
        }
        locationError.value = ''
        commitValidPosition(newLat, newLng)
        address.value = item.display_name || ''
        if (marker && map) {
            marker.setLatLng([newLat, newLng])
            map.setView([newLat, newLng], 15)
        }
    }

    function centerMap() {
        if (map) map.setView(KAUNAS_CENTER, 12)
    }

    function initMap() {
        if (initialized || !containerRef.value || typeof L === 'undefined') return
        map = createBaseMap(containerRef.value, { center: [lat.value, lng.value] })
        if (!map) return
        marker = L.marker([lat.value, lng.value], { draggable: true }).addTo(map)
        marker.on('dragend', () => {
            if (markerLocked.value) return
            const pos = marker.getLatLng()
            if (!isInKaunasBounds(pos.lat, pos.lng)) {
                locationError.value = OUTSIDE_CITY_MESSAGE
                revertToLastValid()
                return
            }
            lat.value = pos.lat
            lng.value = pos.lng
            registerMarkerMove()
            updateAddressFromLatLng(pos.lat, pos.lng)
        })
        map.on('click', (event) => {
            if (markerLocked.value) return
            const { lat: newLat, lng: newLng } = event.latlng
            if (!isInKaunasBounds(newLat, newLng)) {
                locationError.value = OUTSIDE_CITY_MESSAGE
                return
            }
            setMarkerPosition(newLat, newLng)
            registerMarkerMove()
            updateAddressFromLatLng(newLat, newLng)
        })
        initialized = true
        setTimeout(() => map?.invalidateSize(), invalidateSizeDelay)
    }

    function cleanup() {
        if (markerCooldownTimer) clearTimeout(markerCooldownTimer)
        if (reverseAbortController) reverseAbortController.abort()
        if (map) {
            map.remove()
            map = null
            marker = null
            initialized = false
        }
    }

    onMounted(() => loadLeaflet().then(() => nextTick(() => setTimeout(initMap, 100))))
    onUnmounted(cleanup)

    return {
        lat,
        lng,
        address,
        isGeocoding,
        markerLocked,
        locationError,
        setMarkerPosition,
        searchAddress,
        applySuggestionSelection,
        fetchAddressSuggestions,
        centerMap,
    }
}
