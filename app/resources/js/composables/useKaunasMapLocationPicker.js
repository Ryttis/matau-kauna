import { ref, onMounted, onUnmounted, nextTick } from 'vue'

const KAUNAS_CENTER = [54.8985, 23.9036]
const KAUNAS_BOUNDS = [[54.75, 23.65], [55.05, 24.15]]

let leafletLoaded = false
let leafletPromise = null

function loadLeaflet() {
    if (leafletLoaded) return Promise.resolve()
    if (leafletPromise) return leafletPromise
    leafletPromise = new Promise((resolve) => {
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
            leafletLoaded = true
            resolve()
        }
        document.head.appendChild(script)
    })
    return leafletPromise
}

let lastGeocodeAt = 0
const NOMINATIM_DELAY_MS = 1100

async function geocodeAddress(query) {
    if (!query || !query.trim()) return null
    const now = Date.now()
    const wait = Math.max(0, NOMINATIM_DELAY_MS - (now - lastGeocodeAt))
    if (wait) await new Promise((r) => setTimeout(r, wait))
    lastGeocodeAt = Date.now()
    const q = encodeURIComponent(`${query.trim()}, Kaunas`)
    const res = await fetch(
        `https://nominatim.openstreetmap.org/search?q=${q}&format=json&limit=1`,
        { headers: { Accept: 'application/json' } }
    )
    const data = await res.json()
    if (!Array.isArray(data) || data.length === 0) return null
    const [lat, lng] = [parseFloat(data[0].lat), parseFloat(data[0].lon)]
    const address = data[0].display_name || query
    return { lat, lng, address }
}

async function reverseGeocode(lat, lng) {
    const now = Date.now()
    const wait = Math.max(0, NOMINATIM_DELAY_MS - (now - lastGeocodeAt))
    if (wait) await new Promise((r) => setTimeout(r, wait))
    lastGeocodeAt = Date.now()
    const res = await fetch(
        `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`,
        { headers: { Accept: 'application/json' } }
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

    function centerMap() {
        if (map) map.setView(KAUNAS_CENTER, 12)
    }

    function initMap() {
        if (initialized || !containerRef.value || typeof L === 'undefined') return
        map = L.map(containerRef.value, {
            center: [lat.value, lng.value],
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
        map.setMaxZoom(18)
        L.control.zoom({ position: 'topright' }).addTo(map)
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
        initialized = true
        setTimeout(() => { if (map) map.invalidateSize() }, invalidateSizeDelay)
    }

    function cleanup() {
        if (map) {
            map.remove()
            map = null
            marker = null
            initialized = false
        }
    }

    onMounted(() => {
        loadLeaflet().then(() => nextTick(() => setTimeout(initMap, 100)))
    })
    onUnmounted(cleanup)

    return { lat, lng, address, isGeocoding, setMarkerPosition, searchAddress, centerMap }
}
