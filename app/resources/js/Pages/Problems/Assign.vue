<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import SearchableSelect from '@/Components/App/SearchableSelect.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import { useProblemAssignPage } from '@/composables/useProblemAssignPage'
import '@/Components/Problems/assign-page.css'

const props = defineProps({
    problem: {
        type: Object,
        default: null,
    },
    problems: {
        type: Array,
        default: () => [],
    },
    institutions: {
        type: Array,
        default: () => [],
    },
})

const {
    showSuccessModal,
    successMessage,
    closeSuccessModal,
    ICON_ARROW,
    PROBLEM_STATUS,
    institutionColumns,
    selectedInstitutionIds,
    selectedStatus,
    selectedEmailRecipientKey,
    displayedInstitutions,
    emailRecipients,
    emailRecipientSelectOptions,
    emailSelectPlaceholder,
    canSendAssignEmail,
    hasMore,
    isInstitutionsExpanded,
    isStatusExpanded,
    isAssigningInstitution,
    isSendingInstitutionEmail,
    handleTableSelectItem,
    handleTableSelectAll,
    handleLoadMore,
    handleStatusSelect,
    handleSend,
    handleSendEmail,
    handleBack,
    toggleInstitutions,
    toggleStatus,
} = useProblemAssignPage(props)
</script>

<template>
    <Head title="Priskirti problemą" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="assign-page">
            <div class="assign-header">
                <AppBackButton @click="handleBack" />
                <div class="assign-title-wrapper">
                    <h1 class="assign-title">Problemai priskiriama atsakingai įstaigai</h1>
                    <button class="assign-title-icon-btn" type="button" @click="toggleInstitutions">
                        <img
                            :src="ICON_ARROW"
                            alt="Dropdown"
                            class="assign-title-icon"
                            :class="{ 'assign-icon-rotated': !isInstitutionsExpanded }"
                        />
                    </button>
                </div>
            </div>

            <div class="assign-content">
                <div class="assign-section assign-institutions-table-wrap" v-show="isInstitutionsExpanded">
                    <template v-if="displayedInstitutions.length > 0">
                        <EntityTable
                            :items="displayedInstitutions"
                            :columns="institutionColumns"
                            row-key="id"
                            :selectable="true"
                            :single-select="true"
                            :selected-keys="selectedInstitutionIds"
                            @select-item="handleTableSelectItem"
                            @select-all="handleTableSelectAll"
                        />
                        <button
                            v-if="hasMore"
                            class="assign-more-btn"
                            @click="handleLoadMore"
                        >
                            Daugiau
                        </button>
                    </template>
                    <div v-else class="assign-empty-state">
                        <span class="assign-empty-text">Nėra prieinamų institucijų</span>
                    </div>
                </div>

                <div class="assign-section">
                    <div class="assign-section-header">
                        <label class="assign-section-label">Būsena</label>
                        <button class="assign-section-icon-btn" type="button" @click="toggleStatus">
                            <img
                                :src="ICON_ARROW"
                                alt="Dropdown"
                                class="assign-section-icon"
                                :class="{ 'assign-icon-rotated': !isStatusExpanded }"
                            />
                        </button>
                    </div>
                    <div v-show="isStatusExpanded">
                        <div class="assign-status-buttons">
                            <button
                                class="assign-status-btn"
                                :class="{
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.NEW,
                                    'assign-status-btn-new': true,
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.NEW)"
                            >
                                Nauja
                            </button>
                            <button
                                class="assign-status-btn"
                                :class="{
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.IN_REVIEW,
                                    'assign-status-btn-review': true,
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.IN_REVIEW)"
                            >
                                Nagrinėjama
                            </button>
                            <button
                                class="assign-status-btn"
                                :class="{
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.RETURNED_FOR_REVIEW,
                                    'assign-status-btn-returned': true,
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.RETURNED_FOR_REVIEW)"
                            >
                                Grąžinta nagrinėjimui
                            </button>
                        </div>
                        <div class="assign-send-btn-wrap">
                            <AppButton
                                text="Priskirti"
                                variant="primary"
                                as-button
                                class="assign-send-btn"
                                :disabled="selectedInstitutionIds.length === 0 || isSendingInstitutionEmail"
                                :loading="isAssigningInstitution"
                                @click="handleSend"
                            />
                        </div>
                    </div>
                </div>

                <div class="assign-section assign-section-email">
                    <SearchableSelect
                        v-model="selectedEmailRecipientKey"
                        label="Siųsti informaciją el. paštu"
                        :options="emailRecipientSelectOptions"
                        :disabled="!emailRecipients.length"
                        :show-empty-option="false"
                        :empty-option-label="emailSelectPlaceholder"
                        search-placeholder="Ieškoti įstaigos ar el. pašto…"
                        list-max-height="min(50vh, 320px)"
                        :max-display-length="140"
                        class="assign-email-select"
                    />
                    <div class="assign-send-btn-wrap">
                        <AppButton
                            text="Siųsti"
                            variant="primary"
                            as-button
                            class="assign-send-btn"
                            :disabled="!canSendAssignEmail || isAssigningInstitution"
                            :loading="isSendingInstitutionEmail"
                            @click="handleSendEmail"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
