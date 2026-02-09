export const KAUNAS_CENTER = [54.8985, 23.9036]
export const KAUNAS_BOUNDS = [[54.75, 23.65], [55.05, 24.15]]

const LEAFLET_VERSION = '1.9.4'
const LEAFLET_CSS = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.css`
const LEAFLET_JS = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.js`
const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'

let leafletLoaded = false
let leafletPromise = null

export function loadLeaflet() {
    if (leafletLoaded) return Promise.resolve()
    if (leafletPromise) return leafletPromise
    leafletPromise = new Promise((resolve) => {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = LEAFLET_CSS
        link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
        link.crossOrigin = ''
        document.head.appendChild(link)
        const script = document.createElement('script')
        script.src = LEAFLET_JS
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

export function createBaseMap(containerElement, options = {}) {
    if (!containerElement || typeof L === 'undefined') return null
    const {
        center = KAUNAS_CENTER,
        zoom = 12,
        minZoom = 11,
        maxZoom = 18,
    } = options
    const map = L.map(containerElement, {
        center: Array.isArray(center) ? center : KAUNAS_CENTER,
        zoom,
        maxBounds: KAUNAS_BOUNDS,
        maxBoundsViscosity: 1.0,
        zoomControl: false,
    })
    L.tileLayer(TILE_URL, {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map)
    map.setMaxBounds(KAUNAS_BOUNDS)
    map.setMinZoom(minZoom)
    map.setMaxZoom(maxZoom)
    L.control.zoom({ position: 'topright' }).addTo(map)
    return map
}
