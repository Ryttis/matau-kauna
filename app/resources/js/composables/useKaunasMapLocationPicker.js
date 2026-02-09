import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { loadLeaflet, createBaseMap, KAUNAS_CENTER } from '@/composables/leafletKaunas'

const NOMINATIM_DELAY_MS = 1100
let lastGeocodeAt = 0

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
