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
import { ICON_FILTER } from '@/constants/ui/icons'
import { useAuth } from '@/composables/useAuth'
import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'
import { buildInactiveReviewPostBody } from '@/constants/domain/reviewProgressEntry'
import { parseDateFilterInput } from '@/utils/parseDateFilterInput'
import '@/Components/Map/map-page.css'

const props = defineProps({
    problems: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    natures: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
})

const { canSeeProblemAdminButtons, canSeeInstitutionAdminButtons, isMunicipalityRep } = useAuth()

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

const DEFAULT_FILTER_STATE = {
    reportNumber: '',
    problemType: '',
    problemNatureId: '',
    dateFrom: '',
    dateTo: '',
    status: '',
}

const filterState = ref({ ...DEFAULT_FILTER_STATE })
const showFilterModal = ref(false)

const hasActiveFilters = computed(() => {
    const f = filterState.value
    return Boolean(
        (f.reportNumber || '').trim()
        || (f.problemType || '').trim()
        || (f.problemNatureId || '').trim()
        || (f.status || '').trim()
        || (f.dateFrom || '').trim()
        || (f.dateTo || '').trim()
    )
})

const filteredProblems = computed(() => {
    const list = Array.isArray(props.problems) ? props.problems : []
    const f = filterState.value
    const reportNum = (f.reportNumber || '').trim().toLowerCase()
    const natureId = (f.problemNatureId || '').toString().trim()
    const from = parseDateFilterInput(f.dateFrom)
    const to = parseDateFilterInput(f.dateTo)
    return list.filter((problem) => {
        if (reportNum && !(problem.internal_number || '').toLowerCase().includes(reportNum)) return false
        if (f.status && problem.status !== f.status) return false
        if (f.problemType && problem.category !== f.problemType) return false
        if (natureId && String(problem.problem_nature_id ?? '') !== natureId) return false
        if (from || to) {
            const created = problem.created_at ? new Date(problem.created_at) : null
            if (!created) return false
            if (from && created < from) return false
            if (to && created > to) return false
        }
        return true
    })
})

const problemsForMap = computed(() => {
    const sel = selectedProblemFromAddress.value
    if (sel) return [sel]
    return filteredProblems.value
})

function handleFilterApply(filters) {
    filterState.value = {
        reportNumber: filters.reportNumber || '',
        problemType: filters.problemType || '',
        problemNatureId: filters.problemNatureId || '',
        dateFrom: filters.dateFrom || '',
        dateTo: filters.dateTo || '',
        status: filters.status || '',
    }
    showFilterModal.value = false
}

function handleFilterClear() {
    filterState.value = { ...DEFAULT_FILTER_STATE }
}

function openProblemView(problemId) {
    router.visit(route('problems.show', problemId))
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
            <h1 class="map-page-title">Žemėlapis</h1>
            <p class="map-page-desc">Problemų peržiūra žemėlapyje ir paieška.</p>
            <div class="search-filter-section">
                <div class="map-page-search-wrap">
                    <MapAddressAutocomplete
                        variant="pill"
                        search-mode="number"
                        :show-search-icon="true"
                        :reset-token="mapNumberSearchResetToken"
                        placeholder="Problemos Nr."
                        @select="onMapNumberSearchSelect"
                        @clear="onMapNumberSearchClear"
                    />
                </div>
                <button
                    type="button"
                    class="map-page-filter-btn"
                    :aria-label="hasActiveFilters ? 'Filtruoti (yra aktyvių filtrų)' : 'Filtruoti'"
                    @click="showFilterModal = true"
                >
                    <span class="map-page-filter-btn__icon-wrap">
                        <img :src="ICON_FILTER" class="map-page-filter-btn__icon" alt="" />
                        <span v-if="hasActiveFilters" class="map-page-filter-btn__badge" aria-hidden="true" />
                    </span>
                </button>
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
                :filters="{
                    reportNumber: filterState.reportNumber,
                    problemType: filterState.problemType,
                    problemNatureId: filterState.problemNatureId,
                    dateFrom: filterState.dateFrom,
                    dateTo: filterState.dateTo,
                    status: filterState.status,
                }"
                :categories="props.categories"
                :natures="props.natures"
                :all-status-options="props.statusOptions"
                @close="showFilterModal = false"
                @apply="handleFilterApply"
                @clear="handleFilterClear"
            />

        </div>
    </AuthenticatedLayout>
</template>
