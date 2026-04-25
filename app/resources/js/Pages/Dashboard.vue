<script setup>
import AppButton from '@/Components/App/AppButton.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemMapStatusLegend from '@/Components/Map/ProblemMapStatusLegend.vue'
import ProblemsMapList from '@/Components/Map/ProblemsMapList.vue'
import DashboardInternalView from '@/Components/Dashboard/DashboardInternalView.vue'
import SearchFilterBar from '@/Components/App/SearchFilterBar.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { useDashboardReporter } from '@/composables/useDashboardReporter'
import {
    REPORTER_MAP_FILTER_STATUS_OPTIONS,
    REPORTER_MAP_LEGEND_STATUS_ORDER,
} from '@/constants/domain/problemStatus'
import '@/Components/Dashboard/dashboard-page.css'

const page = usePage()

const props = defineProps({
    reporterView: {
        type: Boolean,
        default: false,
    },
    onlyMine: {
        type: Boolean,
        default: false,
    },
    problems: [Object, Array],
    filters: Object,
    sortBy: String,
    sortDirection: String,
    categories: Array,
    statusOptions: Array,
})

const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

onMounted(() => {
    if (!props.reporterView) return
    const url = new URL(page.url, window.location.origin)
    if (url.searchParams.get('report_created') !== '1') return
    showWithMessage('Pranešimas sėkmingai užregistruotas.')
    url.searchParams.delete('report_created')
    const qs = url.searchParams.toString()
    const clean = url.pathname + (qs ? `?${qs}` : '') + url.hash
    window.history.replaceState({}, '', clean)
})
const {
    reporterSearchQuery,
    reporterFilterState,
    reporterSelectedIds,
    reporterCategoryOptions,
    reporterProblemsToShowOnMap,
    reporterToggle,
    reporterDisplayRows,
    reporterToggleGroup,
    reporterShowFilterModal,
    reporterOpenFilterModal,
    reporterHandleFilterApply,
    reporterHandleFilterClear,
    reporterOpenProblemView,
    reporterHandleStebeti,
    reporterToggleWatchFromList,
    isReporterProblemWatched,
    reporterSelectedIsWatched,
    reporterCanToggleWatchSelection,
    reporterWatchLoadingRootId,
    reporterWatchBusy,
    reporterStebetiButtonLoading,
    showOnlyMyReports,
    reporterToggleOnlyMine,
    reporterToggleOnlyMineFromFilter,
    reporterProblems,
} = useDashboardReporter(props)

const currentYear = new Date().getFullYear()
const reporterDateMin = `${currentYear}-01-01`
const reporterDateMax = `${currentYear}-12-31`
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div v-if="reporterView" class="reporter-dashboard">
            <h1 class="reporter-dashboard-title">Žemėlapis</h1>
            <p class="reporter-dashboard-desc">Mano ir viešai skelbiamos problemos žemėlapyje</p>
            <div class="reporter-dashboard-top">
                <div class="reporter-dashboard-toolbar">
                    <SearchFilterBar
                        v-model="reporterSearchQuery"
                        placeholder="Ieškoti"
                        @filter-click="reporterOpenFilterModal"
                    />
                </div>
                <label class="reporter-dashboard-checkbox-label">
                    <input
                        v-model="showOnlyMyReports"
                        type="checkbox"
                        class="reporter-dashboard-checkbox"
                        @change="reporterToggleOnlyMine"
                    />
                    <span>Rodyti tik mano iškeltas problemas</span>
                </label>
            </div>
            <div class="reporter-dashboard-layout">
                <div class="reporter-dashboard-map-wrap">
                    <ProblemMapStatusLegend :status-order="REPORTER_MAP_LEGEND_STATUS_ORDER" />
                    <KaunasProblemsMap
                        :problems="reporterProblemsToShowOnMap"
                        marker-class="reporter-dashboard-marker"
                        @open-problem="reporterOpenProblemView"
                    />
                </div>
                <aside class="reporter-dashboard-list">
                    <h3 class="reporter-dashboard-list-title">Sąrašas</h3>
                    <ProblemsMapList
                        :display-rows="reporterDisplayRows"
                        :all-count="reporterProblems.length"
                        :selected-ids="reporterSelectedIds"
                        actions-header="Veiksmai"
                        view-button-variant="internal"
                        enable-watch
                        :is-watched="isReporterProblemWatched"
                        :watch-loading-root-id="reporterWatchLoadingRootId"
                        @select="reporterToggle"
                        @select-group="reporterToggleGroup"
                        @open="reporterOpenProblemView"
                        @toggle-watch="reporterToggleWatchFromList"
                    />
                    <div class="reporter-dashboard-actions">
                        <AppButton
                            :text="reporterSelectedIsWatched ? 'Nebestebėti' : 'Stebėti eigą'"
                            variant="primary"
                            as-button
                            :disabled="!reporterCanToggleWatchSelection || reporterWatchBusy"
                            :loading="reporterStebetiButtonLoading"
                            @click="reporterHandleStebeti"
                        />
                    </div>
                </aside>
            </div>
            <FilterModal
                :show="reporterShowFilterModal"
                variant="publicMap"
                :filters="reporterFilterState"
                :categories="reporterCategoryOptions"
                :status-checkbox-options="REPORTER_MAP_FILTER_STATUS_OPTIONS"
                :only-mine="showOnlyMyReports"
                :date-min="reporterDateMin"
                :date-max="reporterDateMax"
                @close="reporterShowFilterModal = false"
                @apply="reporterHandleFilterApply"
                @clear="reporterHandleFilterClear"
                @only-mine-change="reporterToggleOnlyMineFromFilter"
            />
        </div>

        <DashboardInternalView
            v-else
            :problems="problems"
            :filters="filters"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            :categories="categories"
            :status-options="statusOptions"
        />
    </AuthenticatedLayout>
</template>
