<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import DeleteAccountModal from '@/Components/App/DeleteAccountModal.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import axios from 'axios'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { ROLE_INSTITUTION_ADMIN, ROLE_EMPLOYEE } from '@/constants/roles.js'

const props = defineProps({
    employees: Array,
    inviteRoles: { type: Array, default: () => [] },
})

const page = usePage()
const user = page.props.auth.user
const authRoles = computed(() => page.props.auth?.roles || [])

const isInstitutionAdmin = computed(() =>
    authRoles.value.some((r) => r.role === ROLE_INSTITUTION_ADMIN)
)
const isEmployee = computed(() =>
    authRoles.value.some((r) => r.role === ROLE_EMPLOYEE)
)
const canManageEmailUpdatesAssigned = computed(
    () => isInstitutionAdmin.value || isEmployee.value
)

const profileForm = useForm({
    name: user.name || '',
    surname: user.surname || '',
    email: user.email || '',
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

const passwordForm = useForm({})

const deleteAccountForm = useForm({
    password: '',
})

const showDeleteModal = ref(false)
const twoFactorEnabled = ref(!!user.two_factor_enabled)
const emailUpdatesInstitutionAdmin = ref(!!user.email_updates_institution_admin)
const emailNotificationsEnabled = ref(!!user.email_notifications_enabled)

const sections = ref({
    profile: true,
    employee: true,
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

const requestPasswordReset = () => {
  // simple post; you can show status flash on success
  passwordForm.post(route('settings.password.email'), {
    preserveScroll: true,
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
const inviteSuccess = ref(false)

const sendInvite = async () => {
    if (!user?.institution_id || !inviteForm.value.email || !inviteForm.value.role_id) return
    inviteError.value = ''
    inviteSuccess.value = false
    inviteSending.value = true
    try {
        await axios.post('/invitations', {
            email: inviteForm.value.email,
            institution_id: user.institution_id,
            role_id: inviteForm.value.role_id,
        })
        inviteSuccess.value = true
        inviteForm.value = { email: '', role_id: '' }
    } catch (e) {
        inviteError.value = e.response?.data?.message ?? 'Nepavyko išsiųsti pakvietimo.'
    } finally {
        inviteSending.value = false
    }
}
</script>

<template>
    <Head title="Nustatymai" />

    <AuthenticatedLayout>
        <div class="settings-container">
            <div class="settings-content">
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
                    title="Pakviesti darbuotoją"
                    :open="sections.invite"
                    @toggle="toggleSection('invite')"
                >
                    <div v-if="inviteSuccess" class="invite-success">Pkvietimas išsiųstas.</div>
                    <div v-if="inviteError" class="invite-error">{{ inviteError }}</div>
                    <form @submit.prevent="sendInvite" class="form-grid">
                        <AppInput v-model="inviteForm.email" type="email" placeholder="El. paštas" :showIcon="true" />
                        <select v-model="inviteForm.role_id" class="settings-select" required>
                            <option value="">— Rolė</option>
                            <option v-for="r in inviteRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
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

                <div class="privacy-block">
                    <SettingsSection
                        title="Privatumo taisyklės"
                        :open="sections.privacy"
                        :noBorder="true"
                        class="privacy-section"
                        @toggle="toggleSection('privacy')"
                    >
                    </SettingsSection>

                    <div class="privacy-row privacy-row-2fa">
                        <span class="privacy-label">2 FA autentifikavimas</span>
                        <div class="privacy-row-2fa-right">
                            <label class="switch">
                                <input v-model="twoFactorEnabled" type="checkbox" />
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    <div
                        v-if="isInstitutionAdmin"
                        class="privacy-row privacy-row-2fa"
                    >
                        <span class="privacy-label">Informacijos atnaujinimo siuntimas el. paštu – Įstaigos administratorius</span>
                        <div class="privacy-row-2fa-right">
                            <label class="switch">
                                <input
                                    v-model="emailUpdatesInstitutionAdmin"
                                    type="checkbox"
                                    @change="saveEmailUpdatesInstitutionAdmin"
                                />
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    <div
                        v-if="canManageEmailUpdatesAssigned"
                        class="privacy-row privacy-row-2fa"
                    >
                        <span class="privacy-label">Pranešimai apie naujai priskirtas problemas el. paštu</span>
                        <div class="privacy-row-2fa-right">
                            <label class="switch">
                                <input
                                    v-model="emailNotificationsEnabled"
                                    type="checkbox"
                                    @change="saveEmailNotificationsEnabled"
                                />
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>

                    <AppButton
                        text="Gauti slaptažodžio keitimo nuorodą el. paštu"
                        variant="link"
                        as-button
                        class="privacy-link"
                        @click="requestPasswordReset"
                    />

                    <div class="privacy-row">
                        <AppButton
                            text="Šalinti paskyrą"
                            variant="link"
                            as-button
                            class="privacy-link"
                            @click="showDeleteModal = true"
                        />
                    </div>
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

        <div v-if="$page.props.flash?.status" style="margin-top: 8px; color: #065F46;">
            {{ $page.props.flash.status }}
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.settings-container {
    max-width: 800px;
    background: #fff;
    padding: 20px;
}

.settings-content {
    max-width: 100%;
    box-sizing: border-box;
}

.invite-success {
    color: #2e7d32;
    font-size: 14px;
    margin-bottom: 12px;
}

.invite-error {
    color: #c62828;
    font-size: 14px;
    margin-bottom: 12px;
}

.settings-select {
    padding: 10px 12px;
    border: 1px solid #e6e8ee;
    border-radius: 10px;
    font-size: 16px;
    min-width: 200px;
}

.form-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    max-width: 400px;
    box-sizing: border-box;
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
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.groups-name {
    font-weight: 500;
}

.groups-members {
    color: #6b7280;
}

.groups-empty {
    color: #6b7280;
    font-size: 14px;
    margin: 12px 0 0;
}

.link-btn.small {
    margin-left: auto;
    padding: 0;
    border: none;
    background: none;
    color: #1946a0;
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

.btn-primary, .btn-secondary {
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.btn-primary {
    background: #1946a0;
    color: #fff;
    border: none;
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-secondary {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
}

.employee-list {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #E5E7EB;
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
    color: #4B5563;
    padding: 4px 0;
}

.privacy-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
    width: 100%;
    box-sizing: border-box;
}

.privacy-block > * {
    margin: 0;
    padding: 0;
    height: 32px;
    min-height: 32px;
    box-sizing: border-box;
}

.privacy-block .privacy-section.section {
    margin-bottom: 0;
    min-height: 32px;
}

.privacy-block .privacy-section :deep(.section-header) {
    margin: 0;
    padding: 0;
    height: 32px;
    min-height: 32px;
    line-height: 32px;
    display: flex;
    align-items: center;
}

.privacy-block .privacy-section :deep(.section-header h2) {
    line-height: 1.2;
}

.privacy-block .privacy-section :deep(.section-content) {
    margin: 0;
    padding: 0;
}

.privacy-row.privacy-row-2fa {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    width: 100%;
    height: 32px;
    min-height: 32px;
    box-sizing: border-box;
    white-space: nowrap;
}

.privacy-label {
    font-size: 18px;
    font-weight: 600;
    color: #000;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.privacy-row-2fa-right {
    justify-self: end;
    width: max-content;
}

.privacy-row:not(.privacy-row-2fa) {
    display: flex;
    align-items: center;
    height: 32px;
    min-height: 32px;
}

.privacy-link {
    width: auto;
    margin: 0;
    padding: 0;
    font-size: 18px;
    font-weight: 600;
    color: #000;
    text-align: left;
    display: inline-block;
    line-height: 32px;
    text-decoration: none;
}

.privacy-link:hover {
    text-decoration: underline;
}

.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 22px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.25s ease;
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.25s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
}

input:checked + .slider {
    background-color: #1946A0;
}

input:checked + .slider:before {
    transform: translateX(22px);
}

.slider.round {
    border-radius: 22px;
}

.slider.round:before {
    border-radius: 50%;
}

</style>
