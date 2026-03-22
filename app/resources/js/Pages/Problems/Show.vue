<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import EditModal from '@/Components/App/Modals/EditModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import AppInput from '@/Components/App/AppInput.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import ProblemMergeLinks from '@/Components/Problems/ProblemMergeLinks.vue'
import ProblemAttachmentsPanel from '@/Components/Problems/ProblemAttachmentsPanel.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { useAuth } from '@/composables/useAuth'
import { ICON_RIGHT_ARROW } from '@/constants/icons'
import { PROBLEM_STATUS } from '@/constants/problemStatus'

const props = defineProps({
    problem: Object,
    mergeTarget: { type: Object, default: null },
    mergedChildren: { type: Array, default: () => [] },
    mergedSiblings: { type: Array, default: () => [] },
    statusOptions: Array,
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
} = useAuth()

const canAssignProblem = computed(() => props.problem?.status !== PROBLEM_STATUS.RESOLVED)

const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
const showStatusModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)

const handleBack = () => {
    router.visit(route('dashboard'))
}

const handleStatusChange = (status) => {
    router.patch(route('problems.updateStatus', props.problem.id), {
        status,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false
        }
    })
}

const openStatusModal = () => {
    showStatusModal.value = true
}

const openEditModal = () => {
    showEditModal.value = true
}

const closeEditModal = () => {
    showEditModal.value = false
}

const handleConfirmDelete = () => {
    router.delete(route('problems.destroy', props.problem.id), {
        preserveScroll: false,
        onSuccess: () => {
            showDeleteModal.value = false
        },
    })
}

const handleGrazintiAdmin = () => {
    router.patch(route('problems.return', props.problem.id), {}, { preserveScroll: true })
}

const handleTogglePublish = () => {
    const next = !props.problem.is_published_publicly
    router.patch(route('problems.updatePublish', props.problem.id), {
        is_published_publicly: next
    }, { preserveScroll: true })
}

const mainProblem = computed(() => props.problem?.merged_into || props.problem?.merged_into_problem || null)
const isMergedIntoOther = computed(
    () => Boolean(props.problem?.merged_into_problem_id && mainProblem.value)
)

const mergeTargetId = computed(() =>
    isMergedIntoOther.value && mainProblem.value ? mainProblem.value.id : props.problem?.id
)

const employeeDisplayName = (emp) => {
    if (!emp) return ''
    const full = [emp.name, emp.surname].filter(Boolean).join(' ').trim()
    return full || emp.email || ''
}

const assignmentInfo = computed(() => {
    const p = props.problem
    if (!p) {
        return { hasAny: false, institutionName: null, employeeName: null }
    }
    const inst = p.institution
    const emp = p.assigned_employee ?? p.assignedEmployee
    const institutionName = inst?.name?.trim() || null
    const employeeName = employeeDisplayName(emp) || null
    return {
        hasAny: Boolean(institutionName || employeeName),
        institutionName,
        employeeName,
    }
})
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
                <AppBackButton @click="handleBack" />
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
                        v-if="assignmentInfo.institutionName"
                        :model-value="assignmentInfo.institutionName"
                        label="Įstaiga"
                        type="text"
                        readonly
                    />
                    <AppInput
                        v-if="assignmentInfo.employeeName"
                        :model-value="assignmentInfo.employeeName"
                        label="Darbuotojas"
                        type="text"
                        readonly
                    />
                    <AppInput
                        v-if="!assignmentInfo.hasAny"
                        model-value="Dar nepriskirta įstaigai arba konkrečiam darbuotojui."
                        label="Skirta"
                        type="text"
                        readonly
                    />
                    <AppInput
                        :model-value="problem.category"
                        label="Kategorija"
                        type="text"
                        readonly
                    />
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

                    <div class="problem-media">
                        <ProblemAttachmentsPanel
                            :attachments="problem.attachments ?? []"
                            :problem-id="problem.id"
                            :can-delete="canEditProblem"
                        />
                    </div>
                </div>
            </div>

            <div class="review-progress-section" @click="() => router.visit(route('problems.reviewProgress', problem.id))">
                <span class="review-progress-text">Nagrinėjimo eiga</span>
                <img :src="ICON_RIGHT_ARROW" alt="Rodyklė" class="review-progress-arrow" />
            </div>

            <div v-if="canSeeProblemAdminButtons" class="problem-actions">
                <AppButton
                    v-if="canAssignProblem"
                    text="Priskirti problemą"
                    variant="primary"
                    as-button
                    @click="() => router.visit(route('problems.assign.single', problem.id))"
                    class="action-btn"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    variant="primary"
                    as-button
                    @click="openStatusModal"
                    class="action-btn"
                />
                <AppButton
                    v-if="canPublish"
                    :text="problem.is_published_publicly ? 'Nebepaskelbti viešai' : 'Paskelbti viešai'"
                    variant="secondary"
                    as-button
                    @click="handleTogglePublish"
                    class="action-btn"
                />
                <AppButton
                    v-if="canEditProblem"
                    text="Koreguoti"
                    variant="secondary"
                    as-button
                    @click="openEditModal"
                    class="action-btn"
                />
                <AppButton
                    v-if="canMergeProblem"
                    text="Apjungti"
                    variant="secondary"
                    as-button
                    @click="() => router.visit(route('problems.merge', mergeTargetId))"
                    class="action-btn"
                />
                <AppButton
                    v-if="canDeleteProblem"
                    text="Šalinti"
                    variant="secondary"
                    as-button
                    @click="showDeleteModal = true"
                    class="action-btn"
                />
            </div>

            <div v-else-if="canSeeInstitutionAdminButtons" class="problem-actions">
                <AppButton
                    v-if="canAssignProblem"
                    text="Priskirti/Peradresuoti problemą"
                    variant="primary"
                    as-button
                    @click="() => router.visit(route('problems.assignInstitution', problem.id))"
                    class="action-btn"
                />
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    variant="primary"
                    as-button
                    @click="openStatusModal"
                    class="action-btn"
                />
                <AppButton
                    text="Apjungti"
                    variant="secondary"
                    as-button
                    @click="() => router.visit(route('problems.merge', mergeTargetId))"
                    class="action-btn"
                />
            </div>

            <div v-else-if="isEmployee" class="problem-actions">
                <AppButton
                    text="Atvaizduoti/keisti būseną"
                    variant="primary"
                    as-button
                    @click="openStatusModal"
                    class="action-btn"
                />
                <AppButton
                    text="Grąžinti problemos administratoriui"
                    variant="primary"
                    as-button
                    @click="handleGrazintiAdmin"
                    class="action-btn"
                />
            </div>

            <StatusChangeModal
                :show="showStatusModal"
                :problem="problem"
                :selected-problem-ids="[]"
                @close="showStatusModal = false"
                @save="handleStatusChange"
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
                @close="showDeleteModal = false"
                @confirm="handleConfirmDelete"
            />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.problem-detail {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.problem-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
    width: 100%;
    min-width: 0;
}

.header-spacer {
    flex: 1;
}

.header-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.header-label {
    font-size: 14px;
    color: #666;
    font-family: 'Inter', sans-serif;
}

.header-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}

.problem-status-section {
    margin-bottom: 20px;
}

.problem-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 32px;
    width: 100%;
    min-width: 0;
}

.problem-content :deep(.app-input-group) {
    min-width: 0;
    max-width: 100%;
}

.problem-fields-top {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 600px;
    width: 100%;
}

.problem-fields-top .app-input-group {
    margin-bottom: 0;
    width: 100%;
}

.problem-content-bottom {
    display: grid;
    grid-template-columns: 1fr minmax(0, 400px);
    grid-template-rows: auto;
    gap: 32px;
    margin-top: 20px;
    align-items: start;
    width: 100%;
    min-width: 0;
}

.problem-content-bottom .app-input-group {
    margin-bottom: 0;
}

.problem-content-bottom .app-input-group textarea {
    height: 150px;
    min-height: 150px;
    max-height: 150px;
    resize: none;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-group-description {
    display: flex;
    flex-direction: column;
    gap: 8px;
    height: 100%;
}

.field-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}


.problem-media {
    height: 150px;
    min-width: 0;
    width: 100%;
    max-width: 100%;
}

.problem-media :deep(.attachments-panel) {
    max-width: 100%;
}

.problem-media :deep(.media-placeholder) {
    max-width: 100%;
    box-sizing: border-box;
}

.media-placeholder {
    width: 100%;
    height: 150px;
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #ffffff 0% 50%) 50% / 20px 20px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
}

.review-progress-section {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    margin-bottom: 20px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.review-progress-section:hover {
    opacity: 0.7;
}

.review-progress-text {
    font-size: 16px;
    font-weight: 400;
    color: #333333;
    font-family: 'Inter', sans-serif;
}

.review-progress-arrow {
    display: block;
    width: 16px;
    height: 16px;
}


.problem-actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: var(--button-grid-gap);
    margin-top: 40px;
    width: 100%;
    min-width: 0;
}

.problem-actions > * {
    margin-bottom: 0 !important;
    min-width: 0;
}

.problem-actions :deep(.action-btn) {
    width: var(--button-width);
    max-width: 100%;
    min-height: var(--button-height);
    box-sizing: border-box;
    border-radius: var(--button-border-radius);
    padding: 10px 20px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Open Sans', sans-serif;
    font-size: 16px;
    font-weight: 400;
    line-height: 1.4;
    letter-spacing: normal;
    text-align: center;
    white-space: normal;
    word-break: break-word;
}

@media (max-width: 900px) {
    .problem-content-bottom {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .problem-media {
        height: auto;
        min-height: 140px;
    }

    .problem-content-bottom :deep(.app-input-group textarea.app-input-edit) {
        max-height: none;
        min-height: 140px;
        height: auto;
    }

    .problem-fields-top {
        max-width: 100%;
    }

    .problem-actions {
        grid-template-columns: 1fr;
    }

    .problem-actions :deep(.action-btn),
    .problem-actions :deep(.app-button) {
        width: 100% !important;
        max-width: 100%;
    }

    .problem-header {
        gap: 12px;
    }

    .header-spacer {
        display: none;
    }

    .header-info-right {
        margin-left: auto;
    }
}

@media (max-width: 480px) {
    .problem-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .header-info-right {
        margin-left: 0;
    }
}
</style>
