const CELL_SIZE_BASE = 0.008
const ZOOM_REF = 14

function cellSize(zoom) {
    const z = Math.max(11, Math.min(18, Number(zoom) || 14))
    return CELL_SIZE_BASE * Math.pow(2, ZOOM_REF - z)
}

function cellKey(lat, lng, size) {
    return `${Math.floor(lat / size)}_${Math.floor(lng / size)}`
}

function centroid(points) {
    const n = points.length
    if (n === 0) return { lat: 0, lng: 0 }
    let lat = 0
    let lng = 0
    for (const p of points) {
        lat += p.lat
        lng += p.lng
    }
    return { lat: lat / n, lng: lng / n }
}

const DISPLAY_COUNT_MAX = 9

export function clusterProblems(problems, zoom) {
    const list = Array.isArray(problems) ? problems : []
    if (list.length === 0) return []

    const size = cellSize(zoom)
    const buckets = new Map()

    for (const p of list) {
        const lat = Number(p.lat)
        const lng = Number(p.lng)
        if (Number.isNaN(lat) || Number.isNaN(lng)) continue
        const key = cellKey(lat, lng, size)
        if (!buckets.has(key)) buckets.set(key, [])
        buckets.get(key).push(p)
    }

    const result = []
    for (const points of buckets.values()) {
        if (points.length === 1) {
            result.push({ type: 'single', problem: points[0] })
        } else {
            const { lat, lng } = centroid(points)
            const count = Math.min(points.length, DISPLAY_COUNT_MAX)
            result.push({ type: 'cluster', lat, lng, count, problems: points })
        }
    }
    return result
}
