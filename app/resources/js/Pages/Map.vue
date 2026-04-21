<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import InactiveReasonModal from '@/Components/App/Modals/InactiveReasonModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemMapStatusLegend from '@/Components/Map/ProblemMapStatusLegend.vue'
import MapAddressAutocomplete from '@/Components/Map/MapAddressAutocomplete.vue'
import { useAuth } from '@/composables/useAuth'
import { ICON_FILTER } from '@/constants/ui/icons'
import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'
import { buildInactiveReviewPostBody } from '@/constants/domain/reviewProgressEntry'
import '@/Components/Map/map-page.css'

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
const inactiveSubmitChannel = ref(null)
const statusPatchSubmitting = ref(false)

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
    if (statusPatchSubmitting.value) return
    if (status === PROBLEM_STATUS.INACTIVE) {
        showStatusModal.value = false
        inactiveComment.value = ''
        showInactiveReasonModal.value = true
        return
    }
    statusPatchSubmitting.value = true
    router.patch(route('problems.updateStatus', selectedProblemFromAddress.value.id), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false
            selectedProblemFromAddress.value = { ...selectedProblemFromAddress.value, status }
        },
        onFinish: () => {
            statusPatchSubmitting.value = false
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
    inactiveSubmitChannel.value = visibility
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
                inactiveSubmitChannel.value = null
            },
        },
    )
}

const handleStatusModalClose = () => {
    if (statusPatchSubmitting.value) return
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

            <ProblemMapStatusLegend />

            <div class="map-container">
                <KaunasProblemsMap
                    :problems="problemsForMap"
                    :highlighted-problem-id="selectedProblemFromAddress?.id ?? null"
                    marker-class="map-page-marker"
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
                :saving="statusPatchSubmitting"
                @close="handleStatusModalClose"
                @save="handleStatusChange"
            />

            <InactiveReasonModal
                v-model:comment="inactiveComment"
                :open="showInactiveReasonModal"
                :submitting="inactiveSubmitting"
                :active-submit-channel="inactiveSubmitChannel"
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
