import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { useAuth } from '@/composables/useAuth'
import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'
import { buildInactiveReviewPostBody } from '@/constants/domain/reviewProgressEntry'

function formatEmployeeDisplayName(employee) {
    if (!employee) return ''
    const fullName = [employee.name, employee.surname].filter(Boolean).join(' ').trim()
    return fullName || employee.email || ''
}

export function useProblemShowPage(props) {
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
    const showInactiveReasonModal = ref(false)
    const inactiveComment = ref('')
    const inactiveSubmitting = ref(false)
    const inactiveSubmitChannel = ref(null)
    const statusSubmitting = ref(false)
    const deleteSubmitting = ref(false)

    const goToDashboard = () => {
        router.visit(route('dashboard'))
    }

    const closeStatusModal = () => {
        if (statusSubmitting.value) return
        showStatusModal.value = false
    }

    const saveProblemStatus = (nextStatus) => {
        if (statusSubmitting.value) return
        if (nextStatus === PROBLEM_STATUS.INACTIVE) {
            showStatusModal.value = false
            inactiveComment.value = ''
            showInactiveReasonModal.value = true
            return
        }

        statusSubmitting.value = true
        router.patch(route('problems.updateStatus', props.problem.id), {
            status: nextStatus,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showStatusModal.value = false
            },
            onFinish: () => {
                statusSubmitting.value = false
            },
        })
    }

    const closeInactiveReasonModal = () => {
        if (inactiveSubmitting.value) return
        showInactiveReasonModal.value = false
    }

    const submitInactiveReason = (visibility) => {
        if (inactiveSubmitting.value) return
        inactiveSubmitting.value = true
        inactiveSubmitChannel.value = visibility
        router.post(
            route('problems.reviewProgress.store', props.problem.id),
            buildInactiveReviewPostBody(inactiveComment.value, visibility),
            {
                preserveScroll: true,
                onSuccess: () => {
                    showInactiveReasonModal.value = false
                    inactiveComment.value = ''
                },
                onFinish: () => {
                    inactiveSubmitting.value = false
                    inactiveSubmitChannel.value = null
                },
            },
        )
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

    const deleteProblemPermanently = () => {
        if (deleteSubmitting.value) return
        deleteSubmitting.value = true
        router.delete(route('problems.destroy', props.problem.id), {
            preserveScroll: false,
            onSuccess: () => {
                showDeleteModal.value = false
            },
            onFinish: () => {
                deleteSubmitting.value = false
            },
        })
    }

    const returnProblemToAdmin = () => {
        router.patch(route('problems.return', props.problem.id), {}, { preserveScroll: true })
    }

    const togglePublicPublishState = () => {
        const willPublish = !props.problem.is_published_publicly
        router.patch(route('problems.updatePublish', props.problem.id), {
            is_published_publicly: willPublish,
        }, { preserveScroll: true })
    }

    const mergeParentProblem = computed(() =>
        props.problem?.merged_into || props.problem?.merged_into_problem || null,
    )

    const problemIsMergedIntoAnother = computed(
        () => Boolean(props.problem?.merged_into_problem_id && mergeParentProblem.value),
    )

    const problemIdForMergePage = computed(() =>
        problemIsMergedIntoAnother.value && mergeParentProblem.value
            ? mergeParentProblem.value.id
            : props.problem?.id,
    )

    const assignmentSummary = computed(() => {
        const problem = props.problem
        if (!problem) {
            return { hasAny: false, institutionName: null, employeeName: null, teamName: null }
        }
        const institution = problem.institution
        const employee = problem.assigned_employee ?? problem.assignedEmployee
        const team = problem.assigned_institution_team ?? problem.assignedInstitutionTeam
        const institutionName = institution?.name?.trim() || null
        const employeeName = formatEmployeeDisplayName(employee) || null
        const teamName = team?.name?.trim() || null
        return {
            hasAny: Boolean(institutionName || employeeName || teamName),
            institutionName,
            employeeName,
            teamName,
        }
    })

    return {
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
    }
}
