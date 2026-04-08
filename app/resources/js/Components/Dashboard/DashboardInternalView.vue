<script setup>
import AppButton from '@/Components/App/AppButton.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import InactiveReasonModal from '@/Components/App/Modals/InactiveReasonModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import ProblemsTable from '@/Components/Problems/ProblemsTable.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'
import { ICON_FILTER, ICON_CIRCLE_ARROW_RIGHT } from '@/constants/ui/icons'
import { useAuth } from '@/composables/useAuth'
import { useDashboardProblemList } from '@/composables/useDashboardProblemList'

const props = defineProps({
    problems: [Object, Array],
    filters: Object,
    sortBy: String,
    sortDirection: String,
    categories: Array,
    statusOptions: Array,
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
    dateFromFilter,
    dateToFilter,
    statusFilter,
    sortBy,
    sortDirection,
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
    submitInactiveReason,
    closeInactiveReasonModal,
    deleteConfirmMessage,
    displayRows,
    selectedIncludesResolvedProblem,
    selectedProblemIdForReview,
    allStatusOptions,
    bulkPublishButtonLabel,
    loadMoreProblems,
    handleSearch,
    handleSort,
    handleStatusChange,
    handleModalClose,
    openStatusModal,
    handleExport,
    handleFilterApply,
    handleFilterClear,
    handleFilterClose,
    handleAssignProblems,
    handleAssignInstitution,
    handleDeleteProblems,
    handleConfirmDelete,
    handleStebeti,
    handleBulkPublishToggle,
    handleSelectAll,
    handleSelectItem,
} = useDashboardProblemList(props)
</script>

<template>
    <div class="dashboard-internal">
    <div class="search-wrp">
        <input
            v-model="search"
            type="text"
            class="search-input"
            placeholder="Ieškoti"
            @input="handleSearch"
        />
        <button
            type="button"
            class="filter-btn"
            aria-label="Filtruoti"
            @click="showFilterModal = true"
        >
            <img :src="ICON_FILTER" class="filter-btn-icon" alt="" />
        </button>
    </div>

    <ProblemsTable
        :problems="problemsList"
        :display-rows="displayRows"
        :selected-problems="selectedProblems"
        :status-labels="PROBLEM_STATUS_LABELS"
        :sort-by="sortBy"
        :sort-direction="sortDirection"
        :can-publish="canPublish"
        select-by-row-click
        @sort="handleSort"
        @select-all="handleSelectAll"
        @select-item="handleSelectItem"
    />

    <div
        v-if="props.problems?.next_page_url && problemsList.length >= 6"
        class="table-footer-wrp"
    >
        <a
            :href="props.problems.next_page_url"
            class="view-more-link"
            @click.prevent="loadMoreProblems"
        >
            <span>Žiūrėti daugiau</span>
            <img :src="ICON_CIRCLE_ARROW_RIGHT" alt="" class="view-more-arrow" />
        </a>
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
                :disabled="selectedProblems.length === 0"
                @click="handleBulkPublishToggle"
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
                text="Stebėti"
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
                text="Stebėti"
                variant="secondary"
                as-button
                class="list-nav-btn"
                :disabled="!selectedProblemIdForReview"
                @click="handleStebeti"
            />
        </template>

        <template v-else>
            <AppButton
                text="Stebėti"
                as-button
                class="list-nav-btn"
                :disabled="!selectedProblemIdForReview"
                @click="handleStebeti"
            />
        </template>
    </div>

    <ConfirmModal
        :open="showDeleteModal"
        :message="deleteConfirmMessage"
        confirm-text="Patvirtinti"
        cancel-text="Atšaukti"
        footer-layout="column"
        confirm-variant="primary"
        width="369px"
        @close="showDeleteModal = false"
        @confirm="handleConfirmDelete"
    />

    <StatusChangeModal
        :show="showStatusModal"
        :problem="selectedProblem"
        :selected-problem-ids="selectedProblems"
        @close="handleModalClose"
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
        :filters="{
            reportNumber: reportNumberFilter,
            problemType: categoryFilter,
            dateFrom: dateFromFilter,
            dateTo: dateToFilter,
            status: statusFilter,
        }"
        :categories="props.categories"
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

.search-wrp {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    align-items: center;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.search-input {
    flex: 1;
    min-width: 0;
    width: auto;
    max-width: 100%;
    height: 44px;
    border: none;
    border-radius: 15px;
    background-color: #f6f7f9;
    color: #333;
    padding: 0 16px;
    font-size: 16px;
}

.filter-btn {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 15px;
    background: #eef0f4;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    box-sizing: border-box;
}

.filter-btn:hover {
    background: #e2e5eb;
}

.filter-btn-icon {
    width: 22px;
    height: 22px;
    display: block;
    object-fit: contain;
}

.table-footer-wrp {
    margin-top: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 16px;
    align-items: center;
}

.view-more-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: #333;
    font-size: 16px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.view-more-link:hover {
    opacity: 0.8;
}

.view-more-arrow {
    display: block;
    width: 20px;
    height: 20px;
    flex-shrink: 0;
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
        flex-wrap: wrap;
        gap: 24px;
    }

    .search-input {
        flex: 0 1 340px;
        max-width: 340px;
    }
}

@media (max-width: 767px) {
    .search-wrp {
        gap: 10px;
    }

    .search-input {
        width: 100%;
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
