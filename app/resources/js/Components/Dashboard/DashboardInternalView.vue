<script setup>
import AppButton from '@/Components/App/AppButton.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import InactiveReasonModal from '@/Components/App/Modals/InactiveReasonModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import SearchFilterBar from '@/Components/App/SearchFilterBar.vue'
import ProblemsTable from '@/Components/Problems/ProblemsTable.vue'
import { useAuth } from '@/composables/useAuth'
import { useDashboardProblemList } from '@/composables/useDashboardProblemList'
import { getStatusBadgeStyle } from '@/constants/domain/problemStatus'

const props = defineProps({
    problems: [Object, Array],
    filters: Object,
    sortBy: String,
    sortDirection: String,
    categories: Array,
    natures: Array,
    institutions: Array,
    statusOptions: Array,
    availableStatusCounts: Object,
    pageSizeOptions: Array,
})

const {
    canSeeProblemAdminButtons,
    canSeeInstitutionAdminButtons,
    canExport,
    canDeleteProblem,
    canPublish,
    isEmployee,
} = useAuth()

const {
    search,
    reportNumberFilter,
    categoryFilter,
    natureFilter,
    institutionFilter,
    dateFromFilter,
    dateToFilter,
    statusesFilter,
    sortBy,
    sortDirection,
    perPage,
    pageSizeOptions,
    currentPage,
    lastPage,
    totalProblems,
    selectedProblems,
    showStatusModal,
    selectedProblem,
    showFilterModal,
    showExportDropdown,
    exportWrapper,
    exportDropdown,
    problemsList,
    showDeleteModal,
    showInactiveReasonModal,
    inactiveComment,
    inactiveSubmitting,
    inactiveSubmitChannel,
    statusChangeSubmitting,
    deleteSubmitting,
    bulkPublishSubmitting,
    submitInactiveReason,
    closeInactiveReasonModal,
    deleteConfirmMessage,
    displayRows,
    selectedIncludesResolvedProblem,
    selectedProblemIdForReview,
    allStatusOptions,
    visibleStatusOptions,
    bulkPublishButtonLabel,
    goToPage,
    handlePerPageChange,
    handleSearch,
    handleSort,
    handleStatusChange,
    handleModalClose,
    openStatusModal,
    handleExport,
    handleFilterApply,
    handleFilterClear,
    handleFilterClose,
    toggleStatusFilter,
    handleAssignProblems,
    handleAssignInstitution,
    handleDeleteProblems,
    handleConfirmDelete,
    handleStebeti,
    handleBulkPublishToggle,
    handleSelectAll,
    handleSelectItem,
    hasActiveListFilters,
} = useDashboardProblemList(props)
</script>

<template>
    <div class="dashboard-internal">
        <h1 class="dashboard-title">Sąrašas</h1>
        <p class="dashboard-desc">
            <template v-if="isEmployee">
                Jums priskirtų ir per komandą paskirstytų problemų peržiūra, filtravimas ir valdymas.
            </template>
            <template v-else>Visų problemų peržiūra, filtravimas ir valdymas.</template>
        </p>
        <div class="search-wrp">
            <SearchFilterBar
                v-model="search"
                placeholder="Ieškoti"
                :filters-active="hasActiveListFilters"
                @input="handleSearch"
                @filter-click="showFilterModal = true"
            />
        </div>
        <div class="dashboard-statuses">
            <span class="dashboard-statuses-label">Būsena</span>
            <div class="dashboard-status-options">
                <button
                    v-for="opt in visibleStatusOptions"
                    :key="opt.value"
                    type="button"
                    class="dashboard-status-pill"
                    :class="{ 'is-active': statusesFilter.includes(opt.value) }"
                    :style="statusesFilter.includes(opt.value) ? getStatusBadgeStyle(opt.value) : null"
                    :aria-pressed="statusesFilter.includes(opt.value)"
                    @click="toggleStatusFilter(opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <ProblemsTable
            :problems="problemsList"
            :display-rows="displayRows"
            :selected-problems="selectedProblems"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            :can-publish="canPublish"
            select-by-row-click
            @sort="handleSort"
            @select-all="handleSelectAll"
            @select-item="handleSelectItem"
        />

        <div class="table-pagination">
            <label class="per-page-control">
                <span>Rodyti:</span>
                <select v-model.number="perPage" class="per-page-select" @change="handlePerPageChange">
                    <option v-for="option in pageSizeOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </label>

            <div class="pagination-controls">
                <button
                    type="button"
                    class="pagination-button"
                    :disabled="currentPage <= 1"
                    @click="goToPage(currentPage - 1)"
                >
                    Ankstesnis
                </button>
                <span class="page-info">{{ currentPage }} / {{ lastPage }} (iš viso {{ totalProblems }})</span>
                <button
                    type="button"
                    class="pagination-button"
                    :disabled="currentPage >= lastPage"
                    @click="goToPage(currentPage + 1)"
                >
                    Kitas
                </button>
            </div>
        </div>

        <div class="list-navigation">
            <template v-if="canSeeProblemAdminButtons">
                <AppButton
                    text="Priskirti problemą"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0 || selectedIncludesResolvedProblem"
                    @click="handleAssignProblems"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="openStatusModal()"
                />
                <AppButton
                    v-if="canDeleteProblem"
                    text="Šalinti"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="handleDeleteProblems"
                />
            </template>

            <template v-else-if="canSeeInstitutionAdminButtons">
                <AppButton
                    text="Priskirti/Peradresuoti problemą"
                    variant="primary"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length !== 1 || selectedIncludesResolvedProblem"
                    @click="handleAssignInstitution"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="openStatusModal()"
                />
                <AppButton
                    text="Nagrinėti"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="!selectedProblemIdForReview"
                    @click="handleStebeti"
                />
            </template>

            <template v-else-if="isEmployee">
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    as-button
                    class="list-nav-btn"
                    :disabled="selectedProblems.length === 0"
                    @click="openStatusModal()"
                />
                <AppButton
                    text="Nagrinėti"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    :disabled="!selectedProblemIdForReview"
                    @click="handleStebeti"
                />
            </template>

            <template v-else>
                <AppButton
                    text="Nagrinėti"
                    as-button
                    class="list-nav-btn"
                    :disabled="!selectedProblemIdForReview"
                    @click="handleStebeti"
                />
            </template>

            <div v-if="canExport" ref="exportWrapper" class="export-wrapper">
                <AppButton
                    text="Eksportuoti į .xlsx"
                    variant="secondary"
                    as-button
                    class="list-nav-btn"
                    @click="showExportDropdown = !showExportDropdown"
                />
                <div v-if="showExportDropdown" ref="exportDropdown" class="export-dropdown">
                    <button type="button" class="export-option" @click="handleExport('all')">
                        Eksportuoti visus rezultatus
                    </button>
                    <button type="button" class="export-option" @click="handleExport('current_page')">
                        Eksportuoti tik esamą puslapį
                    </button>
                </div>
            </div>

            <AppButton
                v-if="canPublish"
                :text="bulkPublishButtonLabel"
                variant="secondary"
                as-button
                class="list-nav-btn"
                :disabled="selectedProblems.length === 0 || bulkPublishSubmitting"
                :loading="bulkPublishSubmitting"
                @click="handleBulkPublishToggle"
            />
        </div>

        <ConfirmModal
            :open="showDeleteModal"
            :message="deleteConfirmMessage"
            confirm-text="Patvirtinti"
            cancel-text="Atšaukti"
            footer-layout="column"
            confirm-variant="primary"
            width="369px"
            :loading="deleteSubmitting"
            @close="showDeleteModal = false"
            @confirm="handleConfirmDelete"
        />

        <StatusChangeModal
            :show="showStatusModal"
            :problem="selectedProblem"
            :selected-problem-ids="selectedProblems"
            :saving="statusChangeSubmitting"
            @close="handleModalClose"
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
            multi-status
            :filters="{
                reportNumber: reportNumberFilter,
                problemType: categoryFilter,
                problemNatureId: natureFilter,
                institutionId: institutionFilter,
                dateFrom: dateFromFilter,
                dateTo: dateToFilter,
                statuses: statusesFilter,
            }"
            :categories="props.categories"
            :natures="props.natures"
            :institutions="props.institutions"
            :all-status-options="allStatusOptions"
            @close="handleFilterClose"
            @apply="handleFilterApply"
            @clear="handleFilterClear"
        />
    </div>
</template>

<style scoped>
.dashboard-internal {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.dashboard-title {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.dashboard-desc {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #666;
}

.search-wrp {
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.dashboard-statuses {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 16px;
    margin: 18px 0 24px;
}

.dashboard-statuses-label {
    flex: 0 0 auto;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.dashboard-status-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    min-width: 0;
}

.dashboard-status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 6px 16px;
    border-radius: 999px;
    border: 1px solid #d0d5dd;
    background: #fff;
    color: #555;
    font-family: 'Open Sans', sans-serif;
    font-size: 14px;
    line-height: 1.4;
    white-space: nowrap;
    cursor: pointer;
    transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
}

.dashboard-status-pill:hover {
    border-color: #9aa3b2;
}

.dashboard-status-pill.is-active {
    border-color: transparent;
    font-weight: 600;
}

.table-pagination {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.per-page-control,
.pagination-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
    font-size: 14px;
}

.per-page-select {
    min-width: 76px;
    padding: 7px 10px;
    border: 1px solid #cfd4dc;
    border-radius: 6px;
    background: #fff;
    font-size: 14px;
}

.pagination-button {
    padding: 7px 14px;
    border: 1px solid var(--color-primary);
    border-radius: 999px;
    background: #fff;
    color: var(--color-primary);
    cursor: pointer;
    font-size: 14px;
}

.pagination-button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.page-info {
    white-space: nowrap;
}

.list-navigation {
    margin: 40px 0;
    width: 100%;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    align-items: center;
    position: relative;
    gap: var(--button-grid-gap, 16px);
    overflow: visible;
}

.list-navigation > * {
    margin-bottom: 0 !important;
}

.list-navigation .list-nav-btn,
.list-navigation .export-wrapper .app-button {
    width: var(--button-width);
    max-width: 100%;
    height: var(--button-height);
    min-height: var(--button-height);
    box-sizing: border-box;
    border-radius: var(--button-border-radius);
    padding: 9px 24px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Open Sans', sans-serif;
    font-weight: 400;
    font-size: 16px;
    line-height: 150%;
    letter-spacing: -0.011em;
    text-align: center;
}

.list-navigation :deep(.app-button--primary),
.list-navigation .export-wrapper :deep(.app-button--primary) {
    background-color: var(--color-primary) !important;
    color: var(--color-white) !important;
}

.list-navigation :deep(.app-button--primary:hover),
.list-navigation :deep(.app-button--primary:focus-visible),
.list-navigation .export-wrapper :deep(.app-button--primary:hover),
.list-navigation .export-wrapper :deep(.app-button--primary:focus-visible) {
    background-color: var(--color-primary-hover) !important;
    color: var(--color-white) !important;
}

.list-navigation :deep(.app-button--secondary),
.list-navigation .export-wrapper :deep(.app-button--secondary) {
    background-color: var(--color-white) !important;
    color: var(--color-text) !important;
    border: 1px solid var(--color-primary) !important;
}

.list-navigation :deep(.app-button--secondary:hover),
.list-navigation :deep(.app-button--secondary:focus-visible),
.list-navigation .export-wrapper :deep(.app-button--secondary:hover),
.list-navigation .export-wrapper :deep(.app-button--secondary:focus-visible) {
    background-color: #f5f5f5 !important;
    color: var(--color-text) !important;
    border: 1px solid var(--color-primary) !important;
}

.list-navigation :deep(.app-button--disabled),
.list-navigation :deep(.app-button--disabled:hover),
.list-navigation .export-wrapper :deep(.app-button--disabled),
.list-navigation .export-wrapper :deep(.app-button--disabled:hover) {
    opacity: 0.5 !important;
}

.export-wrapper {
    position: relative;
}

.export-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    margin-top: 5px;
    background: var(--color-white);
    border: 1px solid var(--color-border);
    border-radius: 15px;
    padding: 5px;
    min-width: 200px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.export-option {
    width: 100%;
    padding: 10px;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    border-radius: 15px;
}

.export-option:hover {
    background-color: #f6f7f9;
}

@media (min-width: 768px) {
    .search-wrp {
        max-width: 400px;
    }
}

@media (max-width: 767px) {
    .dashboard-statuses {
        align-items: flex-start;
        flex-direction: column;
        margin-top: 16px;
    }

    .dashboard-status-options {
        gap: 8px;
        width: 100%;
    }

    .dashboard-status-pill {
        white-space: normal;
        text-align: center;
    }

    .table-pagination {
        align-items: flex-start;
        flex-direction: column;
    }

    .list-navigation {
        grid-template-columns: 1fr;
        margin: 24px 0;
    }

    .list-navigation .list-nav-btn,
    .list-navigation .export-wrapper .app-button {
        width: 100%;
        max-width: 100%;
    }
}
</style>
