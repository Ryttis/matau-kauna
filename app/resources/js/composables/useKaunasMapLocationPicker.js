import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER, KAUNAS_BOUNDS } from '@/composables/leafletKaunas'

const NOMINATIM_DELAY_MS = 1100
const NOMINATIM_HEADERS = { Accept: 'application/json', 'Accept-Language': 'lt' }
let lastGeocodeAt = 0

let NOMINATIM_VIEWBOX = '23.65,55.05,24.15,54.75'
try {
    if (KAUNAS_BOUNDS?.[0] && KAUNAS_BOUNDS?.[1]) {
        const [[minLat, minLon], [maxLat, maxLon]] = KAUNAS_BOUNDS
        NOMINATIM_VIEWBOX = `${minLon},${maxLat},${maxLon},${minLat}`
    }
} catch (_) {}

function isInKaunasBounds(lat, lng) {
    if (!KAUNAS_BOUNDS || KAUNAS_BOUNDS.length < 2) return true
    const [[minLat, minLon], [maxLat, maxLon]] = KAUNAS_BOUNDS
    return lat >= minLat && lat <= maxLat && lng >= minLon && lng <= maxLon
}

function rateLimitWait() {
    const wait = Math.max(0, NOMINATIM_DELAY_MS - (Date.now() - lastGeocodeAt))
    return wait ? new Promise((r) => setTimeout(r, wait)) : Promise.resolve()
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
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v))
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
    const first = data.find((i) => isInKaunasBounds(parseFloat(i.lat), parseFloat(i.lon))) || data[0]
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
    return data.map(parseNominatimItem).filter((i) => isInKaunasBounds(i.lat, i.lng)).slice(0, 8)
}

async function reverseGeocode(lat, lng) {
    await rateLimitWait()
    lastGeocodeAt = Date.now()
    const res = await fetch(
        `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`,
        { headers: NOMINATIM_HEADERS }
    )
    const data = await res.json()
    return data?.display_name ?? ''
}

export function useKaunasMapLocationPicker(containerRef, options = {}) {
    const { initialLat = KAUNAS_CENTER[0], initialLng = KAUNAS_CENTER[1], invalidateSizeDelay = 150 } = options
    const lat = ref(initialLat)
    const lng = ref(initialLng)
    const address = ref('')
    const isGeocoding = ref(false)
    let map = null
    let marker = null
    let initialized = false

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
            if (result) {
                lat.value = result.lat
                lng.value = result.lng
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
        lat.value = newLat
        lng.value = newLng
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
        marker.on('dragend', async () => {
            const pos = marker.getLatLng()
            lat.value = pos.lat
            lng.value = pos.lng
            try {
                const addr = await reverseGeocode(pos.lat, pos.lng)
                if (addr) address.value = addr
            } catch (_) {}
        })
        map.on('click', async (e) => {
            const { lat: newLat, lng: newLng } = e.latlng
            setMarkerPosition(newLat, newLng)
            try {
                const addr = await reverseGeocode(newLat, newLng)
                if (addr) address.value = addr
            } catch (_) {}
        })
        initialized = true
        setTimeout(() => map?.invalidateSize(), invalidateSizeDelay)
    }

    function cleanup() {
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
        setMarkerPosition,
        searchAddress,
        applySuggestionSelection,
        fetchAddressSuggestions,
        centerMap,
    }
}
