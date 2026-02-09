<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import { useAuth } from '@/composables/useAuth'
import { loadLeaflet, createBaseMap } from '@/composables/leafletKaunas'

const props = defineProps({
    categories: Array,
    statusOptions: Array,
})

const { canSeeProblemAdminButtons, canSeeInstitutionAdminButtons, isMunicipalityRep } = useAuth()

const searchQuery = ref('')
const addressInput = ref('')
const mapContainer = ref(null)
const showFilterModal = ref(false)
let map = null
let mapInitialized = false

const filters = ref({
    reportNumber: '',
    problemType: '',
    dateFrom: '',
    dateTo: '',
    status: '',
})

function initMap() {
    if (mapInitialized || !mapContainer.value || typeof L === 'undefined') return
    map = createBaseMap(mapContainer.value)
    if (!map) return
    mapInitialized = true
}

onMounted(() => {
    loadLeaflet().then(() => nextTick(() => setTimeout(initMap, 100)))
})

onUnmounted(() => {
    if (map) {
        map.remove()
        map = null
        mapInitialized = false
    }
})

const handleFilterClick = () => {
    showFilterModal.value = true
}

const handleFilterApply = (appliedFilters) => {
    filters.value = { ...appliedFilters }
    showFilterModal.value = false
}

const handleFilterClear = () => {
    filters.value = {
        reportNumber: '',
        problemType: '',
        dateFrom: '',
        dateTo: '',
        status: '',
    }
    showFilterModal.value = false
}

const handleFilterClose = () => {
    showFilterModal.value = false
}

const handleAssignProblem = () => {}

const handleAssignInstitution = () => {
    router.visit(route('dashboard'))
}

const handleDisplayStatus = () => {}

const handleIeskoti = () => {}
</script>

<template>
    <Head title="Žemėlapis" />

    <AuthenticatedLayout>
        <div class="map-page">
            <div class="search-filter-section">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="search-input"
                    placeholder="ieškoti"
                />
                <img
                    src="/icons/filter-icon.png"
                    class="filter-icon"
                    alt="Filtruoti"
                    @click="handleFilterClick"
                />
            </div>

            <div class="status-indicators">
                <div class="status-indicator status-new">
                    <div class="status-circle status-circle--new"></div>
                    <span class="status-label">Naujos</span>
                </div>
                <div class="status-indicator status-review">
                    <div class="status-circle status-circle--review"></div>
                    <span class="status-label">Nagrinėjamos</span>
                </div>
                <div class="status-indicator status-resolved">
                    <div class="status-circle status-circle--resolved"></div>
                    <span class="status-label">Išspręstos</span>
                </div>
            </div>

            <div class="map-container" ref="mapContainer"></div>

            <div class="location-selection">
                <h3 class="location-title">Pasirinkite problemos vietą</h3>
                <input
                    v-model="addressInput"
                    type="text"
                    class="address-input"
                    placeholder="Suveskite adresą"
                />

                <!-- Problemos administratorius / Sistemos administratorius -->
                <template v-if="canSeeProblemAdminButtons">
                    <AppButton
                        text="Priskirti problemą"
                        as-button
                        @click="handleAssignProblem"
                    />
                    <AppButton
                        text="Atvaizduoti/keisti būseną"
                        as-button
                        @click="handleDisplayStatus"
                    />
                </template>

                <!-- Įstaigos administratorius -->
                <template v-else-if="canSeeInstitutionAdminButtons">
                    <AppButton
                        text="Priskirti/Peradresuoti problemą"
                        as-button
                        @click="handleAssignInstitution"
                    />
                    <AppButton
                        text="Atvaizduoti/keisti būseną"
                        as-button
                        @click="handleDisplayStatus"
                    />
                </template>

                <!-- Įstaigos darbuotojas – tik adresas + keisti būseną -->
                <template v-else-if="!isMunicipalityRep">
                    <AppButton
                        text="Atvaizduoti/keisti būseną"
                        as-button
                        @click="handleDisplayStatus"
                    />
                </template>

                <!-- Savivaldybės atstovas – tik Ieškoti -->
                <template v-else>
                    <AppButton
                        text="Ieškoti"
                        as-button
                        @click="handleIeskoti"
                    />
                </template>
            </div>

            <FilterModal
                :show="showFilterModal"
                :filters="filters"
                :categories="categories || []"
                :all-status-options="statusOptions || []"
                @close="handleFilterClose"
                @apply="handleFilterApply"
                @clear="handleFilterClear"
            />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.map-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 120px);
    gap: 20px;
}

.search-filter-section {
    display: flex;
    gap: 40px;
    align-items: center;
    justify-content: center;
}

.search-input {
    width: 340px;
    height: 40px;
    border: none;
    border-radius: 20px;
    background-color: #F6F7F9;
    color: #333;
    padding: 0 20px;
    font-size: 16px;
}

.filter-icon {
    display: block;
    margin: auto 0;
    cursor: pointer;
}

.status-indicators {
    display: flex;
    gap: 30px;
    align-items: center;
    justify-content: center;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
}

.status-circle--new {
    background-color: #FF6B35;
}

.status-circle--review {
    background-color: #FFD23F;
}

.status-circle--resolved {
    background-color: #06A77D;
}

.status-label {
    font-size: 16px;
    color: #333;
}

.map-container {
    flex: 1;
    width: 100%;
    min-height: 400px;
    border-radius: 8px;
    overflow: hidden;
    z-index: 0;
}

.location-selection {
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: center;
    max-width: 290px;
    margin: 0 auto;
}

.location-title {
    font-size: 18px;
    font-weight: 500;
    color: #333;
    margin: 0;
    text-align: center;
}

.address-input {
    width: 100%;
    height: 45px;
    border: none;
    border-radius: 5px;
    background-color: #FFFFFF;
    color: #333;
    padding: 0 15px;
    font-size: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
</style>
