<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { useAuth } from '@/composables/useAuth'
import { useSuccessModal } from '@/composables/useSuccessModal'

const { canSeeInstitutionAdminButtons } = useAuth()
const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()

const props = defineProps({
    problem: Object,
    employees: {
        type: Array,
        default: () => [],
    },
    institutions: {
        type: Array,
        default: () => [],
    },
})

const selectedEmployeeIds = ref([])
const deadline = ref(
    props.problem?.completion_deadline
        ? (typeof props.problem.completion_deadline === 'string'
            ? props.problem.completion_deadline.slice(0, 10)
            : '')
        : ''
)
const emailForInfo = ref('')
const returnComment = ref('')
const selectedReassignIds = ref([])

const assignSubmitting = ref(false)
const emailSubmitting = ref(false)
const returnSubmitting = ref(false)
const reassignSubmitting = ref(false)

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
    selectedEmployeeIds.value = selectedEmployeeIds.value[0] === id ? [] : [id]
}

const selectInstitution = (id) => {
    selectedReassignIds.value = selectedReassignIds.value[0] === id ? [] : [id]
}

const handleSendAssign = () => {
    if (!selectedEmployeeIds.value.length) return

    assignSubmitting.value = true
    router.post(route('problems.assignInstitution.store', props.problem.id), {
        employee_id: selectedEmployeeIds.value[0],
        completion_deadline: deadline.value || null,
    }, finishVisit(assignSubmitting))
}

const handleSendEmail = () => {
    const email = (emailForInfo.value || '').trim()
    if (!email) {
        alert('Įveskite el. paštą.')
        return
    }
    emailSubmitting.value = true
    router.post(
        route('problems.assignInstitution.sendEmail', props.problem.id),
        { email },
        finishVisit(emailSubmitting)
    )
}

const handleReturnToAdmin = () => {
    returnSubmitting.value = true
    router.patch(route('problems.return', props.problem.id), {}, finishVisit(returnSubmitting))
}

const handleReassign = () => {
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
        const assignedId = newProblem?.assigned_employee_id
        selectedEmployeeIds.value = assignedId ? [assignedId] : []
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
        <div class="assign-institution-page">
            <div class="assign-institution-header">
                <AppBackButton @click="handleBack" />
                <h1 class="assign-institution-title">
                    Priskirti problemą – {{ problem?.internal_number }}
                </h1>
            </div>

            <div class="assign-institution-section">
                <h2 class="section-title">
                    Problemai priskiriama atsakingam darbuotojui/grupei
                </h2>
                <div class="assign-table-scroll" role="region" aria-label="Darbuotojų sąrašas">
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
                                <td>{{ emp.name || '—' }}</td>
                                <td class="assign-select-cell-muted">{{ emp.email || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="assign-empty-state">
                        <span class="assign-empty-text">Nėra duomenų</span>
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
                <h2 class="section-title">Siųsti informaciją el. paštu</h2>
                <div class="field-row field-fixed-width">
                    <AppInput v-model="emailForInfo" type="email" placeholder="El. paštas" />
                </div>
                <div class="btn-row">
                    <AppButton
                        text="Siųsti"
                        variant="primary"
                        as-button
                        :loading="emailSubmitting"
                        @click="handleSendEmail"
                    />
                </div>
            </div>

            <div v-if="canSeeInstitutionAdminButtons" class="assign-institution-section">
                <h2 class="section-title">Grąžinti problemos administratoriui</h2>
                <div class="field-row field-fixed-width">
                    <AppInput
                        v-model="returnComment"
                        :as-textarea="true"
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

            <div class="assign-institution-section">
                <h2 class="section-title">Peradresuoti</h2>
                <div class="assign-table-scroll" role="region" aria-label="Įstaigų sąrašas">
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

.assign-table-scroll {
    width: 100%;
    max-width: 720px;
    max-height: calc(5 * 3rem);
    overflow-y: auto;
    margin-bottom: 16px;
    border: 1px solid var(--color-border, #e5e5e5);
    border-radius: 8px;
    background: var(--color-white, #fff);
    -webkit-overflow-scrolling: touch;
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

.assign-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    text-align: center;
}

.assign-empty-text {
    font-size: 16px;
    color: #666;
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
