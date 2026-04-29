import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useAuth } from '@/composables/useAuth'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { settingsApi } from '@/constants/api/settingsApi'
import { gisSendStatusLabel } from '@/constants/domain/gisTransfer'

export function useAssignInstitutionPage(props) {
    const { canSeeInstitutionAdminButtons } = useAuth()
    const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

    const selectedEmployeeIds = ref([])
    const selectedTeamIds = ref([])
    const deadline = ref(
        props.problem?.completion_deadline
            ? (typeof props.problem.completion_deadline === 'string'
                ? props.problem.completion_deadline.slice(0, 10)
                : '')
            : '',
    )
    const emailForInfo = ref('')

    const employeeEmailOptions = computed(() =>
        (props.employees ?? []).map((emp) => ({
            value: emp.email,
            label: `${emp.name} - ${emp.email}`,
        })),
    )

    const canSendInfoEmail = computed(() => {
        const raw = (emailForInfo.value || '').trim()
        if (!raw) {
            return false
        }
        return (props.employees ?? []).some(
            (e) => String(e.email).toLowerCase() === raw.toLowerCase(),
        )
    })

    const selectedReassignIds = ref([])

    const assignSubmitting = ref(false)
    const emailSubmitting = ref(false)
    const gisSubmitting = ref(false)
    const gisPullSubmitting = ref(false)
    const returnSubmitting = ref(false)
    const reassignSubmitting = ref(false)

    const teamsList = ref([])
    const membersModalOpen = ref(false)
    const membersModalTeam = ref(null)
    const membersModalEmployees = ref([])
    const teamMembersLoading = ref(false)

    watch(
        () => props.teams,
        (t) => {
            teamsList.value = (t ?? []).map((row) => ({ ...row }))
        },
        { immediate: true },
    )

    watch(
        () => props.employees,
        (list) => {
            if (!Array.isArray(list) || list.length === 0) {
                emailForInfo.value = ''
                return
            }
            const cur = (emailForInfo.value || '').trim().toLowerCase()
            const ok = list.some((e) => String(e.email).toLowerCase() === cur)
            if (!ok) {
                emailForInfo.value = list[0].email ?? ''
            }
        },
        { immediate: true, deep: true },
    )

    const finishVisit = (flag) => ({
        preserveScroll: true,
        onFinish: () => {
            flag.value = false
        },
    })

    const handleBack = () => {
        router.visit(route('problems.show', props.problem.id))
    }

    const selectEmployee = (id) => {
        selectedTeamIds.value = []
        selectedEmployeeIds.value = selectedEmployeeIds.value[0] === id ? [] : [id]
    }

    const selectTeam = (id) => {
        selectedEmployeeIds.value = []
        selectedTeamIds.value = selectedTeamIds.value[0] === id ? [] : [id]
    }

    const closeTeamMembersModal = () => {
        membersModalOpen.value = false
        membersModalTeam.value = null
        membersModalEmployees.value = []
    }

    const openTeamMembersModal = async (teamRow) => {
        if (teamMembersLoading.value) return
        teamMembersLoading.value = true
        try {
            const { data } = await axios.get(settingsApi.teams)
            const fullTeam = (data?.teams ?? []).find((t) => t.id === teamRow.id)
            if (!fullTeam) {
                alert('Komanda nerasta.')
                return
            }
            membersModalEmployees.value = data?.employees ?? []
            membersModalTeam.value = fullTeam
            membersModalOpen.value = true
        } catch (e) {
            alert(e.response?.data?.message ?? 'Nepavyko užkrauti komandos duomenų.')
        } finally {
            teamMembersLoading.value = false
        }
    }

    const onTeamMembersSaved = (data) => {
        const idx = teamsList.value.findIndex((x) => x.id === data.id)
        if (idx !== -1) {
            teamsList.value[idx] = {
                ...teamsList.value[idx],
                name: data.name,
                members_count: (data.employees ?? []).length,
            }
        }
        showWithMessage('Komandos nariai atnaujinti.')
    }

    const selectInstitution = (id) => {
        selectedReassignIds.value = selectedReassignIds.value[0] === id ? [] : [id]
    }

    const handleSendAssign = () => {
        if (assignSubmitting.value) {
            return
        }
        const employeeId = selectedEmployeeIds.value[0]
        const teamId = selectedTeamIds.value[0]
        if (!employeeId && !teamId) return

        assignSubmitting.value = true
        const payload = {
            completion_deadline: deadline.value || null,
        }
        if (employeeId) {
            payload.employee_id = employeeId
        } else {
            payload.institution_team_id = teamId
        }
        router.post(route('problems.assignInstitution.store', props.problem.id), payload, finishVisit(assignSubmitting))
    }

    const handleSendEmail = () => {
        if (emailSubmitting.value) {
            return
        }
        if (!canSendInfoEmail.value) {
            alert('Pasirinkite darbuotoją iš sąrašo.')
            return
        }
        const email = (emailForInfo.value || '').trim()
        emailSubmitting.value = true
        router.post(
            route('problems.assignInstitution.sendEmail', props.problem.id),
            { email },
            finishVisit(emailSubmitting),
        )
    }

    const handleReturnToAdmin = () => {
        if (returnSubmitting.value) {
            return
        }
        returnSubmitting.value = true
        router.patch(route('problems.return', props.problem.id), {}, finishVisit(returnSubmitting))
    }

    const handleReassign = () => {
        if (reassignSubmitting.value) {
            return
        }
        if (selectedReassignIds.value.length !== 1) {
            alert('Pasirinkite vieną įstaigą, kuriai perduoti problemą.')
            return
        }
        reassignSubmitting.value = true
        router.post(route('problems.reassignInstitution', props.problem.id), {
            institution_id: selectedReassignIds.value[0],
        }, finishVisit(reassignSubmitting))
    }

    const handleSendToGis = () => {
        gisSubmitting.value = true
        router.post(route('problems.assignInstitution.sendGis', props.problem.id), {}, finishVisit(gisSubmitting))
    }

    const handlePullFromGis = () => {
        if (gisPullSubmitting.value) {
            return
        }
        gisPullSubmitting.value = true
        router.post(route('problems.assignInstitution.pullGis', props.problem.id), {}, finishVisit(gisPullSubmitting))
    }

    watch(
        () => props.problem,
        (newProblem) => {
            const assignedEmp = newProblem?.assigned_employee_id
            const assignedTeam = newProblem?.assigned_institution_team_id
            if (assignedEmp) {
                selectedEmployeeIds.value = [assignedEmp]
                selectedTeamIds.value = []
            } else if (assignedTeam) {
                selectedTeamIds.value = [assignedTeam]
                selectedEmployeeIds.value = []
            } else {
                selectedEmployeeIds.value = []
                selectedTeamIds.value = []
            }
        },
        { immediate: true },
    )

    return {
        canSeeInstitutionAdminButtons,
        showSuccessModal,
        successMessage,
        closeSuccessModal,
        selectedEmployeeIds,
        selectedTeamIds,
        deadline,
        emailForInfo,
        employeeEmailOptions,
        canSendInfoEmail,
        selectedReassignIds,
        assignSubmitting,
        emailSubmitting,
        gisSubmitting,
        gisPullSubmitting,
        returnSubmitting,
        reassignSubmitting,
        gisStatusLabel: gisSendStatusLabel,
        handleSendToGis,
        handlePullFromGis,
        teamsList,
        membersModalOpen,
        membersModalTeam,
        membersModalEmployees,
        teamMembersLoading,
        handleBack,
        selectEmployee,
        selectTeam,
        closeTeamMembersModal,
        openTeamMembersModal,
        onTeamMembersSaved,
        selectInstitution,
        handleSendAssign,
        handleSendEmail,
        handleReturnToAdmin,
        handleReassign,
    }
}
