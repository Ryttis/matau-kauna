<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'

const props = defineProps({
    problems: { type: Array, default: () => [] },
})

const searchQuery = ref('')
const showFilterModal = ref(false)
const selectedIds = ref(new Set())
const mapContainer = ref(null)
let map = null
let mapInitialized = false
let markersLayer = null

const filterState = ref({
    statuses: [],
    problemType: 'all',
    dateFrom: '',
    dateTo: '',
})

const kaunasCenter = [54.8985, 23.9036]
const kaunasBounds = [
    [54.75, 23.65],
    [55.05, 24.15],
]

const allProblems = computed(() => props.problems || [])

const categoryOptions = computed(() => {
    const set = new Set()
    allProblems.value.forEach((p) => { if (p.category) set.add(p.category) })
    return [...set].sort()
})

function parseDate(str) {
    if (!str || !str.trim()) return null
    const s = str.trim()
    const ddmmyy = s.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/)
    if (ddmmyy) return new Date(+ddmmyy[3], +ddmmyy[2] - 1, +ddmmyy[1])
    const iso = new Date(s)
    return isNaN(iso.getTime()) ? null : iso
}

const filteredProblems = computed(() => {
    let list = allProblems.value
    const q = (searchQuery.value || '').trim().toLowerCase()
    if (q) {
        list = list.filter(
            (p) =>
                (p.title || '').toLowerCase().includes(q) ||
                (p.internal_number || '').toLowerCase().includes(q) ||
                (p.address || '').toLowerCase().includes(q)
        )
    }
    if ((filterState.value.statuses || []).length > 0) {
        list = list.filter((p) => filterState.value.statuses.includes(p.status))
    }
    if (filterState.value.problemType && filterState.value.problemType !== 'all') {
        list = list.filter((p) => p.category === filterState.value.problemType)
    }
    const from = parseDate(filterState.value.dateFrom)
    const to = parseDate(filterState.value.dateTo)
    if (from || to) {
        list = list.filter((p) => {
            const created = p.created_at ? new Date(p.created_at) : null
            if (!created) return false
            if (from && created < from) return false
            if (to && created > to) return false
            return true
        })
    }
    return list
})

const problemsToShowOnMap = computed(() => {
    const list = filteredProblems.value
    if (selectedIds.value.size === 0) return list
    return list.filter((p) => selectedIds.value.has(p.id))
})

const markerColorByStatus = {
    nauja: '#e67e22',
    nagrinejama: '#f1c40f',
    isspresta: '#27ae60',
    neuzregistruota: '#95a5a6',
    neaktuali: '#95a5a6',
}
function getMarkerColor(s) {
    return markerColorByStatus[s] || '#95a5a6'
}

function toggle(id) {
    const next = new Set(selectedIds.value)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    selectedIds.value = next
}

function openFilterModal() {
    showFilterModal.value = true
}

function handleFilterApply(filters) {
    filterState.value = { ...filters }
}

function handleFilterClear() {
    filterState.value = { statuses: [], problemType: 'all', dateFrom: '', dateTo: '' }
}

function initMap() {
    if (mapInitialized || !mapContainer.value || typeof L === 'undefined') return
    map = L.map(mapContainer.value, {
        center: kaunasCenter,
        zoom: 12,
        maxBounds: kaunasBounds,
        maxBoundsViscosity: 1.0,
        zoomControl: false,
    })
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map)
    map.setMaxBounds(kaunasBounds)
    map.setMinZoom(11)
    map.setMaxZoom(14)
    L.control.zoom({ position: 'topright' }).addTo(map)
    markersLayer = L.layerGroup().addTo(map)
    mapInitialized = true
    updateMarkers()
}

function updateMarkers() {
    if (!map || !markersLayer || typeof L === 'undefined') return
    markersLayer.clearLayers()
    const list = problemsToShowOnMap.value
    list.forEach((p) => {
        const icon = L.divIcon({
            className: 'public-map-marker',
            html: `<span style="background-color:${getMarkerColor(p.status)};width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.3);display:block;"></span>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7],
        })
        L.marker([p.lat, p.lng], { icon })
            .bindPopup(`<strong>${p.internal_number}</strong><br>${p.title}`)
            .addTo(markersLayer)
    })
    if (list.length === 1) {
        map.setView([list[0].lat, list[0].lng], 14)
    } else if (list.length > 1) {
        const bounds = L.latLngBounds(list.map((p) => [p.lat, p.lng]))
        map.fitBounds(bounds.pad(0.1))
    }
}

watch(problemsToShowOnMap, () => updateMarkers(), { deep: true })

onMounted(() => {
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
    script.onload = () => setTimeout(initMap, 100)
    document.head.appendChild(script)
})

onUnmounted(() => {
    if (map) {
        map.remove()
        map = null
        mapInitialized = false
    }
})
</script>

<template>
    <Head title="Pranešimai – Kauno problemos" />
    <div class="landing public-map-page">
        <LandingHeader active-link="reports" />
        <div class="public-map-toolbar">
            <div class="public-map-toolbar-inner">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="public-map-search"
                    placeholder="ieškoti"
                />
                <button
                    type="button"
                    class="public-map-filter-btn"
                    aria-label="Filtruoti"
                    @click="openFilterModal"
                >
                    <img src="/icons/filter-icon.png" class="public-map-filter-icon" alt="" />
                </button>
            </div>
        </div>
        <div class="public-map-layout">
            <div class="public-map-wrap">
                <div ref="mapContainer" class="public-map-container" />
                <div class="public-map-legend">
                    <span class="public-map-legend-item">
                        <span class="public-map-legend-dot public-map-legend-dot--orange" />
                        Naujos
                    </span>
                    <span class="public-map-legend-item">
                        <span class="public-map-legend-dot public-map-legend-dot--yellow" />
                        Nagrinėjamos
                    </span>
                    <span class="public-map-legend-item">
                        <span class="public-map-legend-dot public-map-legend-dot--green" />
                        Išspręstos
                    </span>
                </div>
            </div>
            <aside class="public-map-list">
                <ul class="public-map-problems">
                    <li
                        v-for="problem in filteredProblems"
                        :key="problem.id"
                        class="public-map-problem"
                    >
                        <label class="public-map-problem-label">
                            <span class="public-map-problem-title">{{ problem.title || problem.internal_number }}</span>
                            <input
                                type="checkbox"
                                :checked="selectedIds.has(problem.id)"
                                class="public-map-checkbox"
                                @change="toggle(problem.id)"
                            />
                        </label>
                    </li>
                    <li v-if="!filteredProblems.length" class="public-map-problem public-map-problem--empty">
                        {{ allProblems.length ? 'Pagal filtrą problemų nerasta' : 'Problemų nėra' }}
                    </li>
                </ul>
            </aside>
        </div>
        <LandingFooter />

        <FilterModal
            :show="showFilterModal"
            variant="publicMap"
            :filters="filterState"
            :categories="categoryOptions"
            @close="showFilterModal = false"
            @apply="handleFilterApply"
            @clear="handleFilterClear"
        />
    </div>
</template>

<style scoped>
.landing {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: 'Open Sans', sans-serif;
    overflow: hidden;
}

.public-map-toolbar {
    flex-shrink: 0;
    background: #fff;
}

.public-map-toolbar-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 12px 1rem;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: 16px;
}

.public-map-search {
    flex: 1;
    min-width: 0;
    max-width: 400px;
    height: 40px;
    border: none;
    border-radius: 20px;
    background: #f6f7f9;
    color: #333;
    padding: 0 16px;
    font-size: 14px;
    box-sizing: border-box;
}

.public-map-search::placeholder {
    color: #999;
}

.public-map-filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    cursor: pointer;
}

.public-map-filter-btn:hover {
    background: #f0f0f0;
}

.public-map-filter-icon {
    width: 24px;
    height: 24px;
}

.public-map-layout {
    flex: 1;
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr);
    grid-template-rows: 1fr;
    gap: 1rem;
    min-height: 0;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 1rem 1rem 2.5rem;
    box-sizing: border-box;
}

@media (max-width: 900px) {
    .public-map-layout {
        grid-template-columns: 1fr;
        max-width: none;
    }
}

.public-map-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #fafafa;
}

.public-map-container {
    flex: 1;
    min-height: 280px;
    max-height: 55vh;
    width: 100%;
    height: 100%;
}

.public-map-container :deep(.leaflet-marker-icon.public-map-marker) {
    background: transparent !important;
    border: none !important;
}

.public-map-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.5rem;
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: #333;
}

.public-map-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.public-map-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.public-map-legend-dot--orange { background: #e67e22; }
.public-map-legend-dot--yellow { background: #f1c40f; }
.public-map-legend-dot--green { background: #27ae60; }

.public-map-list {
    background: #f6f7f9;
    padding: 1.25rem 1rem 1.5rem;
    overflow-y: auto;
    min-height: 0;
    box-sizing: border-box;
}

.public-map-problems {
    list-style: none;
    margin: 0;
    padding: 0 0 0.5rem;
}

.public-map-problem {
    padding: 0.75rem 0;
    border-bottom: 1px solid #e0e0e0;
}

.public-map-problem:last-child {
    border-bottom: none;
}

.public-map-problem--empty {
    color: #666;
    font-size: 0.9375rem;
}

.public-map-problem-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.public-map-problem-title {
    flex: 1;
    font-size: 0.9375rem;
    color: #333;
}

.public-map-checkbox {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}
</style>
