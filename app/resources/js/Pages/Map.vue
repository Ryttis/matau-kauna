<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import InactiveReasonModal from '@/Components/App/Modals/InactiveReasonModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import MapAddressAutocomplete from '@/Components/Map/MapAddressAutocomplete.vue'
import { useAuth } from '@/composables/useAuth'
import { ICON_FILTER } from '@/constants/ui/icons'
import {
    MAP_LEGEND_STATUS_ORDER,
    MARKER_COLOR_BY_STATUS,
    PROBLEM_STATUS,
    PROBLEM_STATUS_LABELS,
} from '@/constants/domain/problemStatus'
import { MAP_LEGEND_DOT_SIZE_PX } from '@/constants/ui/theme'
import { buildInactiveReviewPostBody } from '@/constants/domain/reviewProgressEntry'

const props = defineProps({
    categories: Array,
    statusOptions: Array,
    problems: { type: Array, default: () => [] },
})

const { canSeeProblemAdminButtons, canSeeInstitutionAdminButtons, isMunicipalityRep } = useAuth()

const showFilterModal = ref(false)
const selectedProblemFromAddress = ref(null)
const addressSearchResetToken = ref(0)
const mapNumberSearchResetToken = ref(0)

function onMapNumberSearchSelect(problem) {
    selectedProblemFromAddress.value = problem
    addressSearchResetToken.value += 1
}

function onMapNumberSearchClear() {
    selectedProblemFromAddress.value = null
}

function onAddressSearchSelect(problem) {
    selectedProblemFromAddress.value = problem
    mapNumberSearchResetToken.value += 1
}

function onAddressSearchClear() {
    selectedProblemFromAddress.value = null
}

const problemsForMap = computed(() => {
    const sel = selectedProblemFromAddress.value
    if (sel) return [sel]
    return props.problems || []
})

const filters = ref({
    reportNumber: '',
    problemType: '',
    dateFrom: '',
    dateTo: '',
    status: '',
})

function openProblemView(problemId) {
    router.visit(route('problems.show', problemId))
}

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

const showStatusModal = ref(false)
const showInactiveReasonModal = ref(false)
const inactiveComment = ref('')
const inactiveSubmitting = ref(false)

const canAssignSelectedProblemOnMap = computed(() => {
    const p = selectedProblemFromAddress.value
    return Boolean(p && p.status !== PROBLEM_STATUS.RESOLVED)
})

const handleAssignProblem = () => {
    if (selectedProblemFromAddress.value) {
        router.visit(route('problems.assign.single', selectedProblemFromAddress.value.id))
    }
}

const handleAssignInstitution = () => {
    if (selectedProblemFromAddress.value) {
        router.visit(route('problems.assignInstitution', selectedProblemFromAddress.value.id))
    } else {
        router.visit(route('dashboard'))
    }
}

const handleDisplayStatus = () => {
    if (selectedProblemFromAddress.value) showStatusModal.value = true
}

const handleStatusChange = (status) => {
    if (!selectedProblemFromAddress.value) return
    if (status === PROBLEM_STATUS.INACTIVE) {
        showStatusModal.value = false
        inactiveComment.value = ''
        showInactiveReasonModal.value = true
        return
    }
    router.patch(route('problems.updateStatus', selectedProblemFromAddress.value.id), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false
            selectedProblemFromAddress.value = { ...selectedProblemFromAddress.value, status }
        },
    })
}

const closeInactiveReasonModal = () => {
    if (inactiveSubmitting.value) return
    showInactiveReasonModal.value = false
}

const submitInactiveReason = (visibility) => {
    const p = selectedProblemFromAddress.value
    if (!p || inactiveSubmitting.value) return
    inactiveSubmitting.value = true
    router.post(
        route('problems.reviewProgress.store', p.id),
        buildInactiveReviewPostBody(inactiveComment.value, visibility),
        {
            preserveScroll: true,
            onSuccess: () => {
                showInactiveReasonModal.value = false
                inactiveComment.value = ''
                selectedProblemFromAddress.value = { ...p, status: PROBLEM_STATUS.INACTIVE }
            },
            onFinish: () => {
                inactiveSubmitting.value = false
            },
        },
    )
}

const handleStatusModalClose = () => {
    showStatusModal.value = false
}
</script>

<template>
    <Head title="Žemėlapis" />

    <AuthenticatedLayout>
        <div class="map-page">
            <div class="search-filter-section">
                <div class="map-page-search-wrap">
                    <MapAddressAutocomplete
                        variant="pill"
                        search-mode="number"
                        :reset-token="mapNumberSearchResetToken"
                        placeholder="Problemos Nr. (žemėlapis)"
                        @select="onMapNumberSearchSelect"
                        @clear="onMapNumberSearchClear"
                    />
                </div>
                <img
                    :src="ICON_FILTER"
                    class="filter-icon"
                    alt="Filtruoti"
                    @click="handleFilterClick"
                />
            </div>

            <div class="status-indicators">
                <div
                    v-for="st in MAP_LEGEND_STATUS_ORDER"
                    :key="st"
                    class="status-indicator"
                >
                    <div
                        class="status-circle"
                        :style="{ backgroundColor: MARKER_COLOR_BY_STATUS[st] || '#95a5a6' }"
                    />
                    <span class="status-label">{{ PROBLEM_STATUS_LABELS[st] }}</span>
                </div>
            </div>

            <div class="map-container">
                <KaunasProblemsMap
                    :problems="problemsForMap"
                    :highlighted-problem-id="selectedProblemFromAddress?.id ?? null"
                    marker-class="map-page-marker"
                    :show-legend="false"
                    @open-problem="openProblemView"
                />
            </div>

            <div class="location-selection">
                <div class="map-page-address-block">
                    <h3 class="map-page-location-title">Pasirinkite problemos vietą</h3>
                    <div class="map-page-address-input-wrap">
                        <MapAddressAutocomplete
                            variant="pill-bordered"
                            search-mode="address"
                            :reset-token="addressSearchResetToken"
                            placeholder="Įveskite adresą"
                            @select="onAddressSearchSelect"
                            @clear="onAddressSearchClear"
                        />
                    </div>
                </div>

                <template v-if="selectedProblemFromAddress && canSeeProblemAdminButtons">
                    <AppButton
                        v-if="canAssignSelectedProblemOnMap"
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

                <template v-else-if="selectedProblemFromAddress && canSeeInstitutionAdminButtons">
                    <AppButton
                        v-if="canAssignSelectedProblemOnMap"
                        text="Priskirti/peradresuoti problemą"
                        as-button
                        @click="handleAssignInstitution"
                    />
                    <AppButton
                        text="Atvaizduoti/keisti būseną"
                        as-button
                        @click="handleDisplayStatus"
                    />
                </template>

                <template v-else-if="selectedProblemFromAddress && !isMunicipalityRep">
                    <AppButton
                        text="Atvaizduoti/keisti būseną"
                        as-button
                        @click="handleDisplayStatus"
                    />
                </template>
            </div>

            <StatusChangeModal
                :show="showStatusModal"
                :problem="selectedProblemFromAddress"
                @close="handleStatusModalClose"
                @save="handleStatusChange"
            />

            <InactiveReasonModal
                v-model:comment="inactiveComment"
                :open="showInactiveReasonModal"
                :submitting="inactiveSubmitting"
                @close="closeInactiveReasonModal"
                @submit="submitInactiveReason"
            />

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
    min-height: calc(100vh - 120px);
    gap: 20px;
}

.search-filter-section {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 16px;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
}

.map-page-search-wrap {
    flex: 1 1 240px;
    max-width: 420px;
    min-width: 0;
}

.filter-icon {
    display: block;
    flex-shrink: 0;
    cursor: pointer;
}

.status-indicators {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 20px;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-circle {
    display: block;
    flex-shrink: 0;
    box-sizing: border-box;
    width: var(--map-legend-dot-size, 12px);
    height: var(--map-legend-dot-size, 12px);
    min-width: var(--map-legend-dot-size, 12px);
    min-height: var(--map-legend-dot-size, 12px);
    max-width: var(--map-legend-dot-size, 12px);
    max-height: var(--map-legend-dot-size, 12px);
    border-radius: 50%;
}

.status-label {
    font-size: 16px;
    color: #333;
}

.map-container {
    width: 100%;
    height: 420px;
    min-height: 400px;
    border-radius: 8px;
    overflow: hidden;
    z-index: 0;
}

.map-container :deep(.kaunas-map-container) {
    height: 100%;
    min-height: 400px;
}

.location-selection {
    display: flex;
    flex-direction: column;
    gap: 16px;
    align-items: center;
    max-width: var(--button-width, 290px);
    width: 100%;
    margin: 0 auto;
}

.map-page-address-block {
    width: 100%;
    text-align: center;
    margin-bottom: 4px;
}

.map-page-location-title {
    font-size: 18px;
    font-weight: 500;
    color: #333;
    margin: 0 0 14px;
}

.map-page-address-input-wrap {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
}

</style>
