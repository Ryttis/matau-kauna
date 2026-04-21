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
})

const {
    canSeeInstitutionAdminButtons,
    showSuccessModal,
    successMessage,
    closeSuccessModal,
    selectedEmployeeIds,
    selectedTeamIds,
    deadline,
    emailForInfo,
    returnComment,
    employeeEmailOptions,
    canSendInfoEmail,
    selectedReassignIds,
    assignSubmitting,
    emailSubmitting,
    returnSubmitting,
    reassignSubmitting,
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
} = useAssignInstitutionPage(props)
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
