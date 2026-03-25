const CELL_SIZE_BASE = 0.008
const ZOOM_REF = 14

function cellSize(zoom) {
    const normalizedZoom = Math.max(11, Math.min(18, Number(zoom) || 14))
    return CELL_SIZE_BASE * Math.pow(2, ZOOM_REF - normalizedZoom)
}

function cellKey(lat, lng, size) {
    return `${Math.floor(lat / size)}_${Math.floor(lng / size)}`
}

function centroid(points) {
    const pointCount = points.length
    if (pointCount === 0) return { lat: 0, lng: 0 }
    let latitudeSum = 0
    let longitudeSum = 0
    for (const point of points) {
        latitudeSum += point.lat
        longitudeSum += point.lng
    }
    return { lat: latitudeSum / pointCount, lng: longitudeSum / pointCount }
}

const DISPLAY_COUNT_MAX = 9

export function clusterProblems(problems, zoom) {
    const problemList = Array.isArray(problems) ? problems : []
    if (problemList.length === 0) return []

    const clusterCellSize = cellSize(zoom)
    const buckets = new Map()

    for (const problem of problemList) {
        const latitude = Number(problem.lat)
        const longitude = Number(problem.lng)
        if (Number.isNaN(latitude) || Number.isNaN(longitude)) continue
        const key = cellKey(latitude, longitude, clusterCellSize)
        if (!buckets.has(key)) buckets.set(key, [])
        buckets.get(key).push(problem)
    }

    const clusteredItems = []
    for (const clusterPoints of buckets.values()) {
        if (clusterPoints.length === 1) {
            clusteredItems.push({ type: 'single', problem: clusterPoints[0] })
        } else {
            const { lat, lng } = centroid(clusterPoints)
            const displayCount = Math.min(clusterPoints.length, DISPLAY_COUNT_MAX)
            clusteredItems.push({ type: 'cluster', lat, lng, count: displayCount, problems: clusterPoints })
        }
    }
    return clusteredItems
}
