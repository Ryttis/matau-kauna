import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'
import { ICON_ARROW } from '@/constants/ui/icons'
import { institutionEmailList } from '@/utils/institutionEmails'
import { normalizeStatusForAssignPage } from '@/constants/domain/problemAssign'

export const assignInstitutionTableColumns = [{ key: 'name', label: 'Įstaiga' }]

export function useProblemAssignPage(props) {
    const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
    const selectedInstitutionIds = ref([])
    const selectedStatus = ref(normalizeStatusForAssignPage(props.problem?.status || PROBLEM_STATUS.NEW))
    const selectedEmailRecipientKey = ref('')
    const displayedCount = ref(6)
    const isInstitutionsExpanded = ref(true)
    const isStatusExpanded = ref(true)
    const isAssigningInstitution = ref(false)
    const isSendingInstitutionEmail = ref(false)

    const displayedInstitutions = computed(() => props.institutions.slice(0, displayedCount.value))

    const resolveInitialInstitutionId = () => {
        if (props.problem?.institution_id) {
            return props.problem.institution_id
        }

        if (props.problems?.length) {
            const ids = props.problems
                .map((p) => p.institution_id)
                .filter((id) => id)

            if (!ids.length) return null

            const unique = new Set(ids)
            if (unique.size === 1) {
                return ids[0]
            }
        }

        return null
    }

    const syncInitialSelection = () => {
        const initialId = resolveInitialInstitutionId()
        selectedInstitutionIds.value = initialId ? [initialId] : []
    }

    const emailRecipients = computed(() => {
        const recipients = []
        for (const institution of props.institutions) {
            const emails = institutionEmailList(institution)
            for (const email of emails) {
                recipients.push({
                    key: `${institution.id}::${email}`,
                    institutionId: institution.id,
                    institutionName: institution.name,
                    email,
                })
            }
        }
        return recipients
    })

    const emailRecipientSelectOptions = computed(() =>
        emailRecipients.value.map((r) => ({
            value: r.key,
            label: `${r.institutionName} - ${r.email}`,
        })),
    )

    const selectedEmailRecipient = computed(() =>
        emailRecipients.value.find((item) => item.key === selectedEmailRecipientKey.value) || null,
    )

    const emailSelectPlaceholder = computed(() => {
        if (!emailRecipients.value.length) {
            return 'Nėra įstaigų el. pašto adresų'
        }
        return 'Pasirinkite el. paštą'
    })

    const canSendAssignEmail = computed(() => Boolean(selectedEmailRecipient.value))

    watch(() => [props.problem, props.problems], () => {
        syncInitialSelection()
        if (props.problem) {
            selectedStatus.value = normalizeStatusForAssignPage(
                props.problem.status || PROBLEM_STATUS.NEW,
            )
        }
    }, { immediate: true, deep: true })

    watch(
        () => props.institutions,
        () => {
            selectedEmailRecipientKey.value = emailRecipients.value[0]?.key ?? ''
        },
        { immediate: true, deep: true },
    )

    const hasMore = computed(() => props.institutions.length > displayedCount.value)

    const handleTableSelectItem = (key) => {
        const idx = selectedInstitutionIds.value.indexOf(key)
        if (idx > -1) {
            selectedInstitutionIds.value = []
            return
        }

        selectedInstitutionIds.value = [key]
    }

    const handleTableSelectAll = (checked) => {
        if (!checked) {
            selectedInstitutionIds.value = []
        }
    }

    const handleLoadMore = () => {
        displayedCount.value += 6
    }

    const handleStatusSelect = (status) => {
        selectedStatus.value = status
    }

    const handleSend = () => {
        if (!selectedInstitutionIds.value.length || isAssigningInstitution.value || isSendingInstitutionEmail.value) {
            return
        }

        const ids = props.problems?.length
            ? props.problems.map((p) => p.id)
            : props.problem
                ? [props.problem.id]
                : []

        if (!ids.length) return

        isAssigningInstitution.value = true
        router.post(route('problems.assign.store'), {
            problem_ids: ids,
            institution_id: selectedInstitutionIds.value[0],
            status: selectedStatus.value,
        }, {
            preserveScroll: true,
            onFinish: () => {
                isAssigningInstitution.value = false
            },
        })
    }

    const handleSendEmail = () => {
        const ids = props.problems?.length
            ? props.problems.map((p) => p.id)
            : props.problem
                ? [props.problem.id]
                : []

        if (!canSendAssignEmail.value || !ids.length || isSendingInstitutionEmail.value || isAssigningInstitution.value) {
            return
        }

        isSendingInstitutionEmail.value = true
        router.post(route('problems.assign.email'), {
            email: selectedEmailRecipient.value.email,
            problem_ids: ids,
            institution_id: selectedEmailRecipient.value.institutionId,
        }, {
            preserveScroll: true,
            onFinish: () => {
                isSendingInstitutionEmail.value = false
            },
        })
    }

    const handleBack = () => {
        if (props.problem) {
            router.visit(route('problems.show', props.problem.id))
        } else {
            router.visit(route('dashboard'))
        }
    }

    const toggleInstitutions = () => {
        isInstitutionsExpanded.value = !isInstitutionsExpanded.value
    }

    const toggleStatus = () => {
        isStatusExpanded.value = !isStatusExpanded.value
    }

    return {
        showSuccessModal,
        successMessage,
        closeSuccessModal,
        ICON_ARROW,
        PROBLEM_STATUS,
        institutionColumns: assignInstitutionTableColumns,
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
    }
}
