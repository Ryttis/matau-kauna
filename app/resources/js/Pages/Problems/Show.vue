<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import InactiveReasonModal from '@/Components/App/Modals/InactiveReasonModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import EditModal from '@/Components/App/Modals/EditModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import ProblemMergeLinks from '@/Components/Problems/ProblemMergeLinks.vue'
import ProblemAttachmentsPanel from '@/Components/Problems/ProblemAttachmentsPanel.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { ICON_RIGHT_ARROW } from '@/constants/ui/icons'
import { useProblemShowPage } from '@/composables/useProblemShowPage'
import '@/Components/Problems/problem-show-page.css'

const props = defineProps({
    problem: Object,
    mergeTarget: { type: Object, default: null },
    mergedChildren: { type: Array, default: () => [] },
    mergedSiblings: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    categories: Array,
    fileUpload: {
        type: Object,
        default: () => ({}),
    },
})

const {
    canSeeProblemAdminButtons,
    canSeeInstitutionAdminButtons,
    canEditProblem,
    canMergeProblem,
    canDeleteProblem,
    canPublish,
    isEmployee,
    canAssignProblem,
    showSuccessModal,
    successMessage,
    closeSuccessModal,
    showStatusModal,
    showEditModal,
    showDeleteModal,
    showInactiveReasonModal,
    inactiveComment,
    inactiveSubmitting,
    inactiveSubmitChannel,
    statusSubmitting,
    deleteSubmitting,
    goToDashboard,
    closeStatusModal,
    saveProblemStatus,
    closeInactiveReasonModal,
    submitInactiveReason,
    openStatusModal,
    openEditModal,
    closeEditModal,
    deleteProblemPermanently,
    returnProblemToAdmin,
    togglePublicPublishState,
    problemIdForMergePage,
    assignmentSummary,
} = useProblemShowPage(props)
</script>

<template>
    <Head :title="`Problema ${problem.internal_number}`" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="problem-detail">
            <div class="problem-header">
                <AppBackButton @click="goToDashboard" />
                <div class="header-info header-info-left">
                    <span class="header-label">Nr</span>
                    <span class="header-value">{{ problem.internal_number }}</span>
                </div>
                <div class="header-spacer"></div>
                <div class="header-info header-info-right">
                    <span class="header-label">Data</span>
                    <span class="header-value">{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</span>
                </div>
            </div>

            <div class="problem-status-section">
                <StatusBadge :status="problem?.status" />
                <span
                    class="problem-public-label"
                    :class="{ 'problem-public-label--on': problem.is_published_publicly }"
                >
                    {{ problem.is_published_publicly ? 'Paskelbta viešai' : 'Nepaskelbta viešai' }}
                </span>
            </div>

            <ProblemMergeLinks
                :merge-target="mergeTarget"
                :merged-siblings="mergedSiblings"
                :merged-children="mergedChildren"
                link-route="problems.show"
            />

            <div class="problem-content">
                <div class="problem-fields-top">
                    <AppInput
                        v-if="assignmentSummary.institutionName"
                        :model-value="assignmentSummary.institutionName"
                        label="Įstaiga"
                        type="text"
                        readonly
                    />
                    <AppInput
                        v-if="assignmentSummary.employeeName"
                        :model-value="assignmentSummary.employeeName"
                        label="Darbuotojas"
                        type="text"
                        readonly
                    />
                    <AppInput
                        v-if="assignmentSummary.teamName"
                        :model-value="assignmentSummary.teamName"
                        label="Komanda"
                        type="text"
                        readonly
                    />
                    <AppInput
                        v-if="!assignmentSummary.hasAny"
                        model-value="Dar nepriskirta įstaigai, darbuotojui arba komandai."
                        label="Skirta"
                        type="text"
                        readonly
                    />
                    <div class="problem-show-field-row">
                        <AppInput
                            :model-value="problem.category"
                            label="Kategorija"
                            type="text"
                            readonly
                        />
                        <AppInput
                            :model-value="problem.problem_nature?.name ?? '—'"
                            label="Pobūdis"
                            type="text"
                            readonly
                        />
                    </div>
                    <AppInput
                        :model-value="problem.address"
                        label="Adresas"
                        type="text"
                        readonly
                    />
                </div>

                <div class="problem-content-bottom">
                    <AppInput
                        :model-value="problem.description"
                        label="Aprašymas"
                        :as-textarea="true"
                        :rows="8"
                        readonly
                    />

                    <div
                        v-if="(problem.attachments ?? []).length"
                        class="problem-attachments-below"
                    >
                        <ProblemAttachmentsPanel
                            compact
                            direct-lightbox
                            skip-compact-min-height
                            compact-use-small-thumbs
                            show-compact-open-hint
                            :attachments="problem.attachments"
                            :problem-id="problem.id"
                            :can-delete="false"
                        />
                    </div>
                </div>
            </div>

            <div class="review-progress-section" @click="() => router.visit(route('problems.reviewProgress', problem.id))">
                <span class="review-progress-text">Pranešimų eiga</span>
                <img :src="ICON_RIGHT_ARROW" alt="Rodyklė" class="review-progress-arrow" />
            </div>

            <div v-if="canSeeProblemAdminButtons" class="problem-actions">
                <AppButton
                    v-if="canAssignProblem"
                    text="Priskirti problemą"
                    variant="primary"
                    as-button
                    class="action-btn"
                    @click="() => router.visit(route('problems.assign.single', problem.id))"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    variant="primary"
                    as-button
                    class="action-btn"
                    @click="openStatusModal"
                />
                <AppButton
                    v-if="canPublish"
                    :text="problem.is_published_publicly ? 'Nebepaskelbti viešai' : 'Paskelbti viešai'"
                    variant="secondary"
                    as-button
                    class="action-btn"
                    @click="togglePublicPublishState"
                />
                <AppButton
                    v-if="canEditProblem"
                    text="Koreguoti"
                    variant="secondary"
                    as-button
                    class="action-btn"
                    @click="openEditModal"
                />
                <AppButton
                    v-if="canMergeProblem"
                    text="Apjungti"
                    variant="secondary"
                    as-button
                    class="action-btn"
                    @click="() => router.visit(route('problems.merge', problemIdForMergePage))"
                />
                <AppButton
                    v-if="canDeleteProblem"
                    text="Šalinti"
                    variant="secondary"
                    as-button
                    class="action-btn"
                    @click="showDeleteModal = true"
                />
            </div>

            <div v-else-if="canSeeInstitutionAdminButtons" class="problem-actions">
                <AppButton
                    v-if="canAssignProblem"
                    text="Priskirti/Peradresuoti problemą"
                    variant="primary"
                    as-button
                    class="action-btn"
                    @click="() => router.visit(route('problems.assignInstitution', problem.id))"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    variant="primary"
                    as-button
                    class="action-btn"
                    @click="openStatusModal"
                />
                <AppButton
                    text="Apjungti"
                    variant="secondary"
                    as-button
                    class="action-btn"
                    @click="() => router.visit(route('problems.merge', problemIdForMergePage))"
                />
            </div>

            <div v-else-if="isEmployee" class="problem-actions">
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    variant="primary"
                    as-button
                    class="action-btn"
                    @click="openStatusModal"
                />
                <AppButton
                    text="Grąžinti problemos administratoriui"
                    variant="primary"
                    as-button
                    class="action-btn"
                    @click="returnProblemToAdmin"
                />
            </div>

            <StatusChangeModal
                :show="showStatusModal"
                :problem="problem"
                :selected-problem-ids="[]"
                :saving="statusSubmitting"
                @close="closeStatusModal"
                @save="saveProblemStatus"
            />

            <EditModal
                :show="showEditModal"
                :problem="problem"
                :categories="categories"
                :file-upload="fileUpload"
                @close="closeEditModal"
            />

            <ConfirmModal
                :open="showDeleteModal"
                message="Ar tikrai norite ištrinti šią problemą?"
                confirm-text="Patvirtinti"
                cancel-text="Atšaukti"
                footer-layout="column"
                confirm-variant="primary"
                width="369px"
                :loading="deleteSubmitting"
                @close="showDeleteModal = false"
                @confirm="deleteProblemPermanently"
            />

            <InactiveReasonModal
                v-model:comment="inactiveComment"
                :open="showInactiveReasonModal"
                :submitting="inactiveSubmitting"
                :active-submit-channel="inactiveSubmitChannel"
                @close="closeInactiveReasonModal"
                @submit="submitInactiveReason"
            />
        </div>
    </AuthenticatedLayout>
</template>
