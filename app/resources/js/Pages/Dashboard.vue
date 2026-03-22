<script setup>
import AppButton from '@/Components/App/AppButton.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemsMapList from '@/Components/Map/ProblemsMapList.vue'
import DashboardInternalView from '@/Components/Dashboard/DashboardInternalView.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { useDashboardReporter } from '@/composables/useDashboardReporter'
import { ICON_FILTER, ICON_SEARCH } from '@/constants/icons'

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

const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
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
    reporterSelectedProblemId,
    reporterHandleStebeti,
    showOnlyMyReports,
    reporterToggleOnlyMine,
    reporterToggleOnlyMineFromFilter,
    reporterProblems,
} = useDashboardReporter(props)
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
            <div class="reporter-dashboard-top">
                <div class="reporter-dashboard-toolbar">
                    <div class="reporter-dashboard-search-wrap">
                        <img :src="ICON_SEARCH" class="reporter-dashboard-search-icon" alt="" aria-hidden="true" />
                        <input
                            v-model="reporterSearchQuery"
                            type="text"
                            class="reporter-dashboard-search"
                            placeholder="ieškoti"
                        />
                    </div>
                    <button
                        type="button"
                        class="reporter-dashboard-filter-btn"
                        aria-label="Filtruoti"
                        @click="reporterOpenFilterModal"
                    >
                        <img :src="ICON_FILTER" class="reporter-dashboard-filter-icon" alt="" />
                    </button>
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
                        @select="reporterToggle"
                        @select-group="reporterToggleGroup"
                        @open="reporterOpenProblemView"
                    />
                    <div class="reporter-dashboard-actions">
                        <AppButton
                            text="Stebėti eigą"
                            variant="primary"
                            as-button
                            :disabled="!reporterSelectedProblemId"
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
                :only-mine="showOnlyMyReports"
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

<style scoped>
.reporter-dashboard {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 0;
    flex: 1;
    height: 100%;
    min-height: 500px;
}

.reporter-dashboard-top {
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex-shrink: 0;
}

.reporter-dashboard-toolbar {
    display: flex;
    align-items: center;
    gap: 16px;
}

.reporter-dashboard-search-wrap {
    position: relative;
    flex: 1;
    min-width: 0;
    max-width: 400px;
    display: flex;
    align-items: center;
}

.reporter-dashboard-search-icon {
    position: absolute;
    left: 14px;
    width: 20px;
    height: 20px;
    pointer-events: none;
    object-fit: contain;
}

.reporter-dashboard-search {
    width: 100%;
    height: 40px;
    border: none;
    border-radius: 15px;
    background: #f6f7f9;
    color: #333;
    padding: 0 16px 0 44px;
    font-size: 14px;
    box-sizing: border-box;
}

.reporter-dashboard-search::placeholder {
    color: #999;
}

.reporter-dashboard-filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: none;
    border-radius: 15px;
    background: transparent;
    cursor: pointer;
}

.reporter-dashboard-filter-btn:hover {
    background: #f0f0f0;
}

.reporter-dashboard-filter-icon {
    width: 24px;
    height: 24px;
}

.reporter-dashboard-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    margin: 0;
    padding: 4px 0;
}

.reporter-dashboard-checkbox-label span {
    user-select: none;
}

.reporter-dashboard-checkbox {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    cursor: pointer;
}

.reporter-dashboard-layout {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr);
    gap: 1rem;
    flex: 1;
    min-height: 450px;
    height: 100%;
}

.reporter-dashboard-map-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #fafafa;
}

.reporter-dashboard-map-wrap :deep(.kaunas-map-container) {
    min-height: 350px;
    height: 350px;
}

.reporter-dashboard-map-wrap :deep(.leaflet-marker-icon.reporter-dashboard-marker) {
    background: transparent !important;
    border: none !important;
}

.reporter-dashboard-list {
    background: #f6f7f9;
    padding: 1.25rem 1rem 1.5rem;
    overflow-y: auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.reporter-dashboard-list-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    flex-shrink: 0;
}

.reporter-dashboard-actions {
    flex-shrink: 0;
    display: flex;
    justify-content: center;
}

.reporter-dashboard-actions .app-button {
    width: 100%;
    max-width: 200px;
    height: 40px;
    border-radius: 15px;
    padding: 0 20px;
}

@media (max-width: 900px) {
    .reporter-dashboard-layout {
        grid-template-columns: 1fr;
    }

    .reporter-dashboard-search-wrap {
        max-width: none;
    }
}
</style>
