<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import SettingsPrivacyBlock from '@/Components/App/SettingsPrivacyBlock.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import DeleteAccountModal from '@/Components/App/DeleteAccountModal.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import axios from 'axios'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { ROLE_SYSTEM_ADMIN, ROLE_INSTITUTION_ADMIN } from '@/constants/roles.js'

const props = defineProps({
    employees: Array,
    teams: { type: Array, default: () => [] },
    inviteRoles: { type: Array, default: () => [] },
})

const page = usePage()
const user = page.props.auth?.user
const authRoles = computed(() => page.props.auth?.roles || [])
const activeRole = computed(() => {
    const auth = page.props.auth
    return auth?.activeRole ?? auth?.active_role ?? (authRoles.value?.length === 1 ? authRoles.value[0] : null)
})
const isSystemAdmin = computed(() => (activeRole.value?.role ?? '') === ROLE_SYSTEM_ADMIN)

const roleDisplayNames = (page.props.auth?.roles ?? [])
    .map((r) => r?.name)
    .filter(Boolean)

function isRoleDisplayName(value) {
    if (!value || typeof value !== 'string') return false
    const v = value.trim()
    return roleDisplayNames.some((rn) => rn && String(rn).trim() === v)
}

const isInstitutionAdmin = computed(() =>
    authRoles.value.some((r) => r.role === ROLE_INSTITUTION_ADMIN)
)

const profileForm = useForm({
    name: isRoleDisplayName(user?.name) ? '' : (user?.name || ''),
    surname: isRoleDisplayName(user?.surname) ? '' : (user?.surname || ''),
    email: user?.email || '',
})

const employeeForm = useForm({
    name: '',
    surname: '',
    email: '',
})

const institutionForm = useForm({
    name: user.institution?.name || '',
    code: user.institution?.code || '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const deleteAccountForm = useForm({
    password: '',
})

const privacyForm = useForm({})

const showDeleteModal = ref(false)
const twoFactorEnabled = ref(!!user.two_factor_enabled)
const emailUpdatesInstitutionAdmin = ref(!!user.email_updates_institution_admin)
const emailNotificationsEnabled = ref(!!user.email_notifications_enabled)

const sections = ref({
    profile: true,
    password: true,
    employee: true,
    groups: true,
    invite: true,
    institution: true,
    privacy: true,
})

const toggleSection = (section) => {
    sections.value[section] = !sections.value[section]
}

const updateProfile = () => {
    profileForm.patch(route('settings.profile'), {
        preserveScroll: true,
    })
}

const storeEmployee = () => {
    employeeForm.post(route('settings.employees'), {
        preserveScroll: true,
        onSuccess: () => employeeForm.reset(),
    })
}

const updateInstitution = () => {
    institutionForm.patch(route('settings.institution'), {
        preserveScroll: true,
    })
}

const updatePrivacy = () => {
    privacyForm.patch(route('settings.privacy'), {
        preserveScroll: true,
    })
}

const updatePassword = () => {
    passwordForm.put(route('settings.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

const deleteAccount = () => {
    deleteAccountForm.delete(route('settings.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            deleteAccountForm.reset()
        },
    })
}

const saveEmailUpdatesInstitutionAdmin = () => {
    router.patch(route('settings.privacy'), {
        email_updates_institution_admin: emailUpdatesInstitutionAdmin,
    }, { preserveScroll: true })
}

const saveEmailNotificationsEnabled = () => {
    router.patch(route('settings.privacy'), {
        email_notifications_enabled: emailNotificationsEnabled,
    }, { preserveScroll: true })
}

const inviteForm = ref({ email: '', role_id: '' })
const inviteError = ref('')
const inviteSending = ref(false)
const teams = ref(props.teams ? [...props.teams] : [])
const teamForm = ref({ name: '' })
const teamError = ref('')
const teamSaving = ref(false)
const teamMembersOpen = ref(false)
const selectedTeam = ref(null)
const selectedMemberIds = ref([])
const teamMembersSaving = ref(false)
const teamEditOpen = ref(false)
const editTeamForm = ref({ id: null, name: '' })
const teamEditSaving = ref(false)

const sendInvite = async () => {
    if (!user?.institution_id || !inviteForm.value.email || !inviteForm.value.role_id) return
    inviteError.value = ''
    inviteSending.value = true
    try {
        await axios.post('/invitations', {
            email: inviteForm.value.email,
            institution_id: user.institution_id,
            role_id: inviteForm.value.role_id,
        })
        inviteForm.value = { email: '', role_id: '' }
    } catch (e) {
        inviteError.value = e.response?.data?.message ?? 'Nepavyko išsiųsti pakvietimo.'
    } finally {
        inviteSending.value = false
    }
}

const createTeam = async () => {
    if (!teamForm.value.name) return
    teamError.value = ''
    teamSaving.value = true
    try {
        const { data } = await axios.post(route('settings.teams'), {
            name: teamForm.value.name,
        })
        teams.value = [...teams.value, data].sort((a, b) => a.name.localeCompare(b.name))
        teamForm.value.name = ''
    } catch (e) {
        teamError.value = e.response?.data?.message ?? 'Nepavyko sukurti grupės.'
    } finally {
        teamSaving.value = false
    }
}

const openTeamMembers = (team) => {
    selectedTeam.value = team
    selectedMemberIds.value = (team.employees ?? []).map((e) => e.id)
    teamMembersOpen.value = true
}

const openTeamEdit = (team) => {
    editTeamForm.value = { id: team.id, name: team.name }
    teamEditOpen.value = true
}

const saveTeamEdit = async () => {
    if (!editTeamForm.value.id || !editTeamForm.value.name) return
    teamError.value = ''
    teamEditSaving.value = true
    try {
        const { data } = await axios.put(
            route('settings.teams.update', editTeamForm.value.id),
            { name: editTeamForm.value.name }
        )
        teams.value = teams.value
            .map((t) => (t.id === data.id ? data : t))
            .sort((a, b) => a.name.localeCompare(b.name))
        teamEditOpen.value = false
    } catch (e) {
        teamError.value = e.response?.data?.message ?? 'Nepavyko pervadinti grupės.'
    } finally {
        teamEditSaving.value = false
    }
}

const saveTeamMembers = async () => {
    if (!selectedTeam.value) return
    teamError.value = ''
    teamMembersSaving.value = true
    try {
        const { data } = await axios.post(route('settings.teams.members', selectedTeam.value.id), {
            employee_ids: selectedMemberIds.value,
        })
        teams.value = teams.value.map((t) => (t.id === data.id ? data : t))
        teamMembersOpen.value = false
    } catch (e) {
        teamError.value = e.response?.data?.message ?? 'Nepavyko atnaujinti narių.'
    } finally {
        teamMembersSaving.value = false
    }
}

const deleteTeam = async (team) => {
    if (!team) return
    if (!confirm(`Ar tikrai norite pašalinti grupę \"${team.name}\"?`)) return
    teamError.value = ''
    teamSaving.value = true
    try {
        await axios.delete(route('settings.teams.destroy', team.id))
        teams.value = teams.value.filter((t) => t.id !== team.id)
    } catch (e) {
        teamError.value = e.response?.data?.message ?? 'Nepavyko pašalinti grupės.'
    } finally {
        teamSaving.value = false
    }
}
</script>

<template>
    <Head title="Nustatymai" />

    <AuthenticatedLayout>
        <div class="settings-container settings-page">
            <div class="settings-content">
                <div class="settings-inner">
                    <template v-if="!isSystemAdmin">
                    <SettingsSection
                        title="Profilio duomenys"
                        :open="sections.profile"
                        @toggle="toggleSection('profile')"
                    >
                    <form @submit.prevent="updateProfile" class="form-grid">
                        <AppInput v-model="profileForm.name" placeholder="Vardas" :showIcon="true" />
                        <AppInput v-model="profileForm.surname" placeholder="Pavardė" :showIcon="true" />
                        <AppInput v-model="profileForm.email" type="email" placeholder="El. paštas" :showIcon="true" />
                        <AppButton
                            text="Saugoti"
                            variant="primary"
                            :disabled="profileForm.processing"
                        />
                    </form>
                </SettingsSection>

                <SettingsSection
                    title="Keisti slaptažodį"
                    :open="sections.password"
                    @toggle="toggleSection('password')"
                >
                    <form @submit.prevent="updatePassword" class="form-grid">
                        <AppInput
                            v-model="passwordForm.current_password"
                            type="password"
                            placeholder="Esamas slaptažodis"
                            :show-icon="true"
                        />
                        <AppInput
                            v-model="passwordForm.password"
                            type="password"
                            placeholder="Naujas slaptažodis"
                            :show-icon="true"
                        />
                        <AppInput
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            placeholder="Pakartoti slaptažodį"
                            :show-icon="true"
                        />
                        <div v-if="passwordForm.errors.password" class="form-error">
                            {{ passwordForm.errors.password }}
                        </div>
                        <AppButton
                            text="Patvirtinti"
                            variant="primary"
                            :disabled="passwordForm.processing"
                        />
                    </form>
                </SettingsSection>
                    </template>

                <SettingsSection
                    v-if="isInstitutionAdmin"
                    title="Darbuotojas"
                    :open="sections.employee"
                    @toggle="toggleSection('employee')"
                >
                    <form @submit.prevent="storeEmployee" class="form-grid">
                        <AppInput v-model="employeeForm.name" placeholder="Vardas" />
                        <AppInput v-model="employeeForm.surname" placeholder="Pavardė" />
                        <AppInput v-model="employeeForm.email" type="email" placeholder="El. paštas" />
                        <AppButton
                            text="Saugoti"
                            variant="primary"
                            :disabled="employeeForm.processing"
                        />
                    </form>
                    <div v-if="employees && employees.length > 0" class="employee-list">
                        <h3>Darbuotojų sąrašas:</h3>
                        <ul>
                            <li v-for="emp in employees" :key="emp.id">
                                {{ emp.name }} {{ emp.surname }} ({{ emp.email }})
                            </li>
                        </ul>
                    </div>
                </SettingsSection>

                <SettingsSection
                    v-if="isInstitutionAdmin"
                    title="Darbuotojų grupės"
                    :open="sections.groups"
                    @toggle="toggleSection('groups')"
                >
                    <div v-if="teamError" class="invite-error">{{ teamError }}</div>
                    <form @submit.prevent="createTeam" class="form-grid form-grid-inline">
                        <AppInput v-model="teamForm.name" placeholder="Grupės pavadinimas" />
                        <AppButton
                            text="Sukurti grupę"
                            variant="primary"
                            :disabled="teamSaving || !teamForm.name"
                        />
                    </form>
                    <div v-if="teams && teams.length > 0" class="employee-list">
                        <h3>Grupių sąrašas:</h3>
                        <ul class="groups-list">
                            <li v-for="team in teams" :key="team.id" class="groups-item">
                                <span class="groups-name">{{ team.name }}</span>
                                <span class="groups-members">({{ team.employees?.length ?? 0 }} darbuotojai)</span>
                                <button type="button" class="link-btn small" @click="openTeamMembers(team)">
                                    Nariai
                                </button>
                                <button type="button" class="link-btn small" @click="openTeamEdit(team)">
                                    Pervadinti
                                </button>
                                <button type="button" class="link-btn small" @click="deleteTeam(team)">
                                    Šalinti
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div v-else class="groups-empty">Grupių dar nėra.</div>
                </SettingsSection>

                <SettingsSection
                    v-if="isInstitutionAdmin"
                    title="Pakviesti darbuotoją"
                    :open="sections.invite"
                    @toggle="toggleSection('invite')"
                >
                    <div v-if="inviteError" class="invite-error">{{ inviteError }}</div>
                    <form @submit.prevent="sendInvite" class="form-grid">
                        <AppInput v-model="inviteForm.email" type="email" placeholder="El. paštas" :showIcon="true" />
                        <AppSelect v-model="inviteForm.role_id" placeholder="Rolė" variant="white" class="settings-select">
                            <option v-for="r in inviteRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </AppSelect>
                        <AppButton
                            text="Siųsti pakvietimą"
                            variant="primary"
                            :disabled="inviteSending || !inviteForm.email || !inviteForm.role_id"
                        />
                    </form>
                </SettingsSection>

                <SettingsSection
                    v-if="isInstitutionAdmin"
                    title="Įstaigos nustatymai"
                    :open="sections.institution"
                    @toggle="toggleSection('institution')"
                >
                    <form @submit.prevent="updateInstitution" class="form-grid">
                        <AppInput v-model="institutionForm.name" placeholder="Pavadinimas" :showIcon="true" />
                        <AppInput v-model="institutionForm.code" placeholder="Kodas" :showIcon="true" />
                        <AppButton
                            text="Saugoti"
                            variant="primary"
                            :disabled="institutionForm.processing"
                        />
                    </form>
                </SettingsSection>

                <SettingsPrivacyBlock
                    :two-factor-enabled="twoFactorEnabled"
                    :email-updates-institution-admin="emailUpdatesInstitutionAdmin"
                    :email-notifications-enabled="emailNotificationsEnabled"
                    :is-institution-admin="isInstitutionAdmin"
                    :is-system-admin="isSystemAdmin"
                    @update:twoFactorEnabled="twoFactorEnabled = $event"
                    @update:emailUpdatesInstitutionAdmin="emailUpdatesInstitutionAdmin = $event"
                    @update:emailNotificationsEnabled="emailNotificationsEnabled = $event"
                    @save-email-updates-admin="saveEmailUpdatesInstitutionAdmin"
                    @save-email-notifications="saveEmailNotificationsEnabled"
                    @delete-account="showDeleteModal = true"
                />
                </div>
            </div>
        </div>

        <DeleteAccountModal
            :open="showDeleteModal"
            :confirm-loading="deleteAccountForm.processing"
            @close="showDeleteModal = false"
        >
            <p class="modal-description">Šio veiksmo atšaukti negalėsite.</p>
            <div class="modal-inputs">
                <AppInput
                    v-model="deleteAccountForm.password"
                    type="password"
                    placeholder="Įveskite slaptažodį patvirtinimui"
                />
                <div v-if="deleteAccountForm.errors.password" class="error">
                    {{ deleteAccountForm.errors.password }}
                </div>
            </div>
            <template #footer>
                <AppButton
                    text="Atšaukti"
                    variant="secondary"
                    as-button
                    @click="showDeleteModal = false"
                />
                <AppButton
                    text="Šalinti"
                    variant="primary"
                    as-button
                    :disabled="deleteAccountForm.processing"
                    class="btn-delete"
                    @click="deleteAccount"
                />
            </template>
        </DeleteAccountModal>

        <BaseModal
            :open="teamMembersOpen"
            title="Grupės nariai"
            @close="teamMembersOpen = false"
        >
            <div class="members-checklist">
                <label
                    v-for="emp in employees"
                    :key="emp.id"
                    class="members-check"
                >
                    <input type="checkbox" :value="emp.id" v-model="selectedMemberIds" />
                    <span>{{ emp.name }} {{ emp.surname }} ({{ emp.email }})</span>
                </label>
            </div>
            <template #footer>
                <AppButton
                    text="Atšaukti"
                    variant="secondary"
                    as-button
                    @click="teamMembersOpen = false"
                />
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="teamMembersSaving"
                    @click="saveTeamMembers"
                />
            </template>
        </BaseModal>

        <BaseModal
            :open="teamEditOpen"
            title="Pervadinti grupę"
            @close="teamEditOpen = false"
        >
            <form @submit.prevent="saveTeamEdit" class="form-grid">
                <AppInput v-model="editTeamForm.name" placeholder="Grupės pavadinimas" />
            </form>
            <template #footer>
                <AppButton
                    text="Atšaukti"
                    variant="secondary"
                    as-button
                    @click="teamEditOpen = false"
                />
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="teamEditSaving || !editTeamForm.name"
                    @click="saveTeamEdit"
                />
            </template>
        </BaseModal>

    </AuthenticatedLayout>
</template>

<style scoped>
.settings-page {
    --settings-container-max: 640px;
    --settings-inner-max: 400px;
    --settings-button-width: 260px;
    --settings-button-height: 40px;
    --settings-radius: 15px;
    --settings-input-radius: 10px;
}

.settings-container {
    width: 100%;
    max-width: var(--settings-container-max);
    margin: 0 auto;
    background: var(--color-white);
    padding: 24px;
    box-sizing: border-box;
}

.settings-content {
    width: 100%;
    box-sizing: border-box;
}

.settings-inner {
    width: 100%;
    max-width: var(--settings-inner-max);
    margin: 0 auto;
    box-sizing: border-box;
}

.invite-error,
.form-error {
    color: var(--color-error);
    font-size: 14px;
    margin-bottom: 0;
}

.settings-select {
    padding: 10px 12px;
    border: 1px solid var(--color-border-light);
    border-radius: var(--settings-input-radius);
    font-size: 16px;
    min-width: 200px;
}

.form-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    max-width: var(--settings-inner-max);
    box-sizing: border-box;
    align-items: flex-start;
}

.settings-page .form-grid :deep(.app-input-group) {
    width: 100% !important;
    max-width: 100%;
    box-sizing: border-box;
}

.settings-page .form-grid :deep(.app-input-wrapper-edit),
.settings-page .form-grid :deep(.app-input-edit) {
    width: 100%;
    box-sizing: border-box;
}

.settings-page .form-grid :deep(.app-button) {
    width: var(--settings-button-width) !important;
    max-width: var(--settings-button-width) !important;
    height: var(--settings-button-height) !important;
    min-height: var(--settings-button-height) !important;
    border-radius: var(--settings-radius) !important;
    padding: 0 24px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;
    font-family: 'Open Sans', sans-serif !important;
    font-weight: 400 !important;
}

.settings-page .form-grid :deep(.app-button--primary) {
    background-color: var(--color-primary) !important;
    color: var(--color-white) !important;
}

.settings-page :deep(.app-input-edit),
.settings-page :deep(.app-input-wrapper-edit .app-input-edit) {
    background-color: var(--color-white) !important;
    border: 1px solid var(--color-border) !important;
    border-radius: var(--settings-input-radius) !important;
}

.settings-page :deep(.app-input-wrapper-edit .app-input-icon) {
    width: 20px !important;
    height: 20px !important;
}

.form-grid-inline {
    flex-direction: row;
    align-items: flex-end;
    flex-wrap: wrap;
}

.groups-list {
    list-style: none;
    padding: 0;
    margin: 12px 0 0;
}

.groups-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid var(--color-border);
    font-size: 14px;
}

.groups-name {
    font-weight: 500;
}

.groups-members {
    color: var(--color-gray-500);
}

.groups-empty {
    color: var(--color-gray-500);
    font-size: 14px;
    margin: 12px 0 0;
}

.link-btn.small {
    margin-left: auto;
    padding: 0;
    border: none;
    background: none;
    color: var(--color-primary);
    cursor: pointer;
    font-size: 13px;
}

.link-btn.small:hover {
    text-decoration: underline;
}

.members-checklist {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 320px;
    overflow: auto;
}

.members-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}

.employee-list {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--color-border);
}

.employee-list h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.employee-list ul {
    list-style: none;
    padding: 0;
}

.employee-list li {
    font-size: 14px;
    color: var(--color-text-muted);
    padding: 4px 0;
}

</style>
