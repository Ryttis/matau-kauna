<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import SearchableSelect from '@/Components/App/SearchableSelect.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import InstitutionTeamMembersModal from '@/Components/InstitutionTeams/InstitutionTeamMembersModal.vue'
import { useAssignInstitutionPage } from '@/composables/useAssignInstitutionPage'

const props = defineProps({
    problem: Object,
    employees: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
    institutions: {
        type: Array,
        default: () => [],
    },
    gisAvailable: {
        type: Boolean,
        default: false,
    },
    gisTransfer: {
        type: Object,
        default: null,
    },
})

const {
    canSeeInstitutionAdminButtons,
    showSuccessModal,
    successMessage,
    closeSuccessModal,
} = useAssignInstitutionPage(props)

const selectedEmployeeIds = ref([])
const selectedTeamIds = ref([])
const deadline = ref(
    props.problem?.completion_deadline
        ? (typeof props.problem.completion_deadline === 'string'
            ? props.problem.completion_deadline.slice(0, 10)
            : '')
        : ''
)
const emailForInfo = ref('')
const returnComment = ref('')

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
    { immediate: true }
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
        finishVisit(emailSubmitting)
    )
}

const gisStatusLabel = (status) => {
    switch (status) {
        case 'pending':
            return 'Ruošiama'
        case 'feature_created':
            return 'Objektas sukurtas'
        case 'sent':
            return 'Išsiųsta'
        case 'attachment_failed':
            return 'Priedų klaida'
        case 'failed':
            return 'Klaida'
        default:
            return status || 'Nėra'
    }
}

const handleSendToGis = () => {
    gisSubmitting.value = true
    router.post(route('problems.assignInstitution.sendGis', props.problem.id), {}, finishVisit(gisSubmitting))
}

const gisPullSubmitting = ref(false)

const handlePullFromGis = () => {
    if (gisPullSubmitting.value) return
    gisPullSubmitting.value = true
    router.post(route('problems.assignInstitution.pullGis', props.problem.id), {}, finishVisit(gisPullSubmitting))
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
    { immediate: true }
)
</script>

<template>
    <Head :title="`Priskirti problemą – ${problem?.internal_number}`" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <InstitutionTeamMembersModal
            :open="membersModalOpen"
            :team="membersModalTeam"
            :institution-employees="membersModalEmployees"
            @close="closeTeamMembersModal"
            @saved="onTeamMembersSaved"
        />
        <div class="assign-institution-page">
            <div class="assign-institution-header">
                <AppBackButton @click="handleBack" />
                <h1 class="assign-institution-title">
                    Priskirti problemą – {{ problem?.internal_number }}
                </h1>
            </div>

            <div class="assign-institution-section">
                <h2 class="section-title">
                    Problemos priskyrimas
                </h2>
                <div class="assign-tables-grid">
                    <div class="assign-table-panel" role="region" aria-label="Pavieniai darbuotojai">
                        <h3 class="assign-table-panel-title">Pavieniai darbuotojai</h3>
                        <div class="assign-table-scroll">
                            <table v-if="employees.length" class="assign-select-table">
                                <thead>
                                    <tr>
                                        <th>Darbuotojas</th>
                                        <th>El. paštas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        tabindex="0"
                                        class="assign-select-row"
                                        :class="{ 'assign-select-row--active': selectedEmployeeIds[0] === emp.id }"
                                        @click="selectEmployee(emp.id)"
                                        @keydown.enter.prevent="selectEmployee(emp.id)"
                                        @keydown.space.prevent="selectEmployee(emp.id)"
                                    >
                                        <td>{{ emp.name || '-' }}</td>
                                        <td class="assign-select-cell-muted">{{ emp.email || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="assign-empty-state">
                                <span class="assign-empty-text">Nėra duomenų</span>
                            </div>
                        </div>
                    </div>
                    <div class="assign-table-panel" role="region" aria-label="Komandos">
                        <h3 class="assign-table-panel-title">Komandos</h3>
                        <div class="assign-table-scroll">
                            <table v-if="teamsList.length" class="assign-select-table">
                                <thead>
                                    <tr>
                                        <th>Komanda</th>
                                        <th>Nariai</th>
                                        <th class="assign-select-th-actions">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="t in teamsList"
                                        :key="t.id"
                                        tabindex="0"
                                        class="assign-select-row"
                                        :class="{ 'assign-select-row--active': selectedTeamIds[0] === t.id }"
                                        @click="selectTeam(t.id)"
                                        @keydown.enter.prevent="selectTeam(t.id)"
                                        @keydown.space.prevent="selectTeam(t.id)"
                                    >
                                        <td>{{ t.name || '-' }}</td>
                                        <td class="assign-select-cell-muted">{{ t.members_count }}</td>
                                        <td class="assign-select-actions" @click.stop>
                                            <button
                                                type="button"
                                                class="link-btn"
                                                :disabled="teamMembersLoading"
                                                @click="openTeamMembersModal(t)"
                                            >
                                                Peržiūrėti
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="assign-empty-state">
                                <span class="assign-empty-text">Nėra komandų</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="field-row field-fixed-width">
                    <AppDatePicker
                        v-model="deadline"
                        label="Atlikimo terminas"
                        placeholder="Pasirinkite datą"
                    />
                </div>
                <div class="btn-row">
                    <AppButton
                        text="Priskirti"
                        variant="primary"
                        as-button
                        :loading="assignSubmitting"
                        @click="handleSendAssign"
                    />
                </div>
            </div>

            <div class="assign-institution-section">
                <h2 class="section-title">Perduoti į GIS</h2>
                <div v-if="gisAvailable" class="gis-transfer-card">
                    <p class="gis-transfer-text">
                        GIS būsena:
                        <strong>{{ gisStatusLabel(gisTransfer?.send_status) }}</strong>
                    </p>
                    <p v-if="gisTransfer?.external_object_id" class="gis-transfer-text">
                        Išorinis objekto ID: {{ gisTransfer.external_object_id }}
                    </p>
                    <p v-if="gisTransfer?.external_global_id" class="gis-transfer-text">
                        GlobalID: {{ gisTransfer.external_global_id }}
                    </p>
                    <p v-if="gisTransfer?.last_error" class="gis-transfer-error">
                        Paskutinė klaida: {{ gisTransfer.last_error }}
                    </p>
                    <p
                        v-if="gisTransfer?.attachment_transfers?.length"
                        class="gis-transfer-text"
                    >
                        Priedų įrašų: {{ gisTransfer.attachment_transfers.length }}
                    </p>
                    <div class="btn-row">
                        <AppButton
                            :text="gisTransfer?.external_object_id ? 'Siųsti priedus dar kartą' : 'Siųsti į GIS'"
                            variant="primary"
                            as-button
                            :loading="gisSubmitting"
                            @click="handleSendToGis"
                        />
                        <AppButton
                            v-if="gisTransfer?.external_object_id"
                            text="Patikrinti GIS statusą"
                            variant="secondary"
                            as-button
                            :loading="gisPullSubmitting"
                            @click="handlePullFromGis"
                        />
                    </div>
                </div>
                <div v-else class="assign-empty-state assign-empty-state--left">
                    <span class="assign-empty-text">Šiai įstaigai GIS siuntimas dar nesukonfigūruotas.</span>
                </div>
            </div>

            <div class="assign-institution-section assign-institution-section--email">
                <h2 class="section-title">Siųsti informaciją el. paštu</h2>
                <div class="field-row field-fixed-width assign-institution-email-row">
                    <SearchableSelect
                        v-model="emailForInfo"
                        :options="employeeEmailOptions"
                        :disabled="!employeeEmailOptions.length"
                        :show-empty-option="false"
                        empty-option-label="Pasirinkite darbuotoją"
                        search-placeholder="Ieškoti darbuotojo ar el. pašto…"
                        list-max-height="min(50vh, 320px)"
                        :max-display-length="110"
                        class="assign-institution-email-searchable"
                    />
                </div>
                <div class="btn-row">
                    <AppButton
                        text="Siųsti"
                        variant="primary"
                        as-button
                        :disabled="!canSendInfoEmail || emailSubmitting"
                        :loading="emailSubmitting"
                        @click="handleSendEmail"
                    />
                </div>
            </div>

            <div v-if="canSeeInstitutionAdminButtons" class="assign-institution-section assign-institution-section--return">
                <h2 class="section-title">Grąžinti problemos administratoriui</h2>
                <div class="assign-return-two-col-shell">
                    <div class="assign-return-two-col-shell__column">
                        <div class="field-row assign-return-comment-row assign-return-comment">
                            <AppInput
                                v-model="returnComment"
                                :as-textarea="true"
                                :rows="12"
                                placeholder="Įrašykite komentarą"
                            />
                        </div>
                        <div class="btn-row">
                            <AppButton
                                text="Siųsti"
                                variant="primary"
                                as-button
                                :loading="returnSubmitting"
                                @click="handleReturnToAdmin"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="assign-institution-section assign-institution-section--reassign">
                <h2 class="section-title">Peradresuoti</h2>
                <div class="assign-return-two-col-shell">
                    <div class="assign-return-two-col-shell__column assign-reassign-column">
                        <div class="assign-table-scroll assign-reassign-table" role="region" aria-label="Įstaigų sąrašas">
                            <table v-if="institutions.length" class="assign-select-table">
                                <thead>
                                    <tr>
                                        <th>Įstaiga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="inst in institutions"
                                        :key="inst.id"
                                        tabindex="0"
                                        class="assign-select-row"
                                        :class="{ 'assign-select-row--active': selectedReassignIds[0] === inst.id }"
                                        @click="selectInstitution(inst.id)"
                                        @keydown.enter.prevent="selectInstitution(inst.id)"
                                        @keydown.space.prevent="selectInstitution(inst.id)"
                                    >
                                        <td>{{ inst.name }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="assign-empty-state">
                                <span class="assign-empty-text">Nėra duomenų</span>
                            </div>
                        </div>
                        <div class="btn-row">
                            <AppButton
                                text="Siųsti"
                                variant="primary"
                                as-button
                                :loading="reassignSubmitting"
                                @click="handleReassign"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.assign-institution-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 32px;
}

.assign-institution-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.assign-institution-title {
    font-size: 24px;
    font-weight: 400;
    color: var(--color-text, #333);
    margin: 0;
}

.assign-institution-section {
    margin-bottom: 28px;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text, #333);
    margin: 0 0 12px 0;
}

.assign-tables-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 16px;
    align-items: stretch;
}

.assign-table-panel-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text, #333);
    margin: 0 0 8px 0;
}

.assign-table-scroll {
    width: 100%;
    max-height: calc(5 * 3rem);
    overflow-y: auto;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: 8px;
    background: var(--color-white, #fff);
    -webkit-overflow-scrolling: touch;
}

.assign-reassign-column {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
}

.assign-reassign-table {
    max-width: none;
    width: 100%;
    max-height: min(220px, calc(4 * 2.5rem));
}

.assign-reassign-column .assign-select-table thead th,
.assign-reassign-column .assign-select-row td {
    padding: 8px 12px;
    font-size: 14px;
}

.assign-select-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
}

.assign-select-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    text-align: left;
    padding: 12px 14px;
    background: var(--table-header-bg, var(--color-bg-subtle, #f6f7f9));
    color: var(--table-header-text, #111827);
    font-weight: 600;
    font-size: 14px;
    border-bottom: 1px solid var(--color-border, #e5e5e5);
}

.assign-select-row {
    cursor: pointer;
    outline: none;
}

.assign-select-row td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    color: var(--color-text, #333);
    vertical-align: middle;
}

.assign-select-row:last-child td {
    border-bottom: none;
}

.assign-select-row:hover {
    background: var(--color-bg-subtle, #f6f7f9);
}

.assign-select-row--active {
    background: color-mix(in srgb, var(--color-primary, #1946A0) 16%, white);
}

.assign-select-row--active:hover {
    background: color-mix(in srgb, var(--color-primary, #1946A0) 22%, white);
}

.assign-select-cell-muted {
    color: var(--color-text-muted, #555);
    font-size: 14px;
}

.assign-select-th-actions {
    width: 1%;
    white-space: nowrap;
}

.assign-select-actions {
    vertical-align: middle;
}

.link-btn {
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 13px;
    color: var(--color-primary, #1946a0);
    cursor: pointer;
}

.link-btn:hover:not(:disabled) {
    text-decoration: none;
}

.link-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.assign-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    text-align: center;
}

.assign-empty-state--left {
    justify-content: flex-start;
    text-align: left;
    padding: 0;
}

.assign-empty-text {
    font-size: 16px;
    color: #666;
}

.gis-transfer-card {
    width: 100%;
    max-width: 720px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 18px 20px;
    border-radius: 12px;
    border: 1px solid var(--color-border, #e5e5e5);
    background: color-mix(in srgb, var(--color-primary, #1946A0) 5%, white);
    box-sizing: border-box;
}

.gis-transfer-text,
.gis-transfer-error {
    margin: 0;
    font-size: 15px;
    line-height: 1.4;
}

.gis-transfer-error {
    color: #b42318;
}

.field-fixed-width {
    width: var(--button-width, 290px);
    max-width: 100%;
}

.field-row {
    margin-bottom: 12px;
}

.field-row :deep(.app-input-group) {
    max-width: 100%;
}

.field-row :deep(.app-input-edit),
.field-row :deep(input) {
    width: 100%;
    box-sizing: border-box;
}

.assign-institution-section--email {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.assign-institution-email-row {
    width: 100%;
    max-width: var(--button-width, 290px);
}

.assign-institution-email-row :deep(.searchable-select) {
    flex: 0 0 auto;
    width: 100%;
}

.assign-institution-email-row :deep(.searchable-select__input) {
    border-radius: var(--button-border-radius, 15px);
    font-size: 16px;
}

.assign-institution-email-row :deep(.searchable-select__dropdown) {
    border-radius: var(--button-border-radius, 15px);
}

.assign-return-two-col-shell {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    width: 100%;
}

.assign-return-two-col-shell__column {
    min-width: 0;
}

.assign-return-comment-row {
    width: 100%;
    max-width: 100%;
}

.assign-return-comment :deep(textarea.app-input-edit) {
    min-height: 18rem;
    resize: vertical;
    line-height: 1.45;
}

.btn-row {
    width: var(--button-width, 290px);
    max-width: 100%;
}

.btn-row .app-button {
    width: 100%;
}

@media (max-width: 767px) {
    .assign-institution-page {
        padding: 0 max(1rem, env(safe-area-inset-right)) 1rem max(1rem, env(safe-area-inset-left));
        min-width: 0;
    }

    .assign-institution-header {
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .assign-institution-title {
        font-size: clamp(1.65rem, 6vw, 2rem);
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .section-title {
        font-size: clamp(1.2rem, 4.8vw, 1.45rem);
        line-height: 1.3;
    }

    .assign-tables-grid,
    .assign-return-two-col-shell {
        grid-template-columns: 1fr;
    }

    .assign-table-scroll {
        max-height: 280px;
    }

    .assign-select-table {
        font-size: 14px;
    }

    .assign-select-table thead th,
    .assign-select-row td {
        padding: 10px 12px;
    }

    .field-fixed-width,
    .btn-row {
        width: 100%;
    }
}
</style>
