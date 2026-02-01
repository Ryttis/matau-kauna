<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import DeleteAccountModal from '@/Components/App/DeleteAccountModal.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    employees: Array,
})

const page = usePage()
const user = page.props.auth.user
const authRoles = computed(() => page.props.auth?.roles || [])

const isInstitutionAdmin = computed(() =>
    authRoles.value.some((r) => r.role === 'ROLE_INSTITUTION_ADMIN')
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

const deleteAccountForm = useForm({
    password: '',
})

const showDeleteModal = ref(false)
const twoFactorEnabled = ref(!!user.two_factor_enabled)
const emailUpdatesInstitutionAdmin = ref(!!user.email_updates_institution_admin)

const sections = ref({
    profile: true,
    employee: true,
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

                <div class="privacy-row">
                    <Link :href="route('settings.change-password')" class="privacy-link">
                        Keisti slaptažodį
                    </Link>
                </div>

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
            @confirm="deleteAccount"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.settings-container {
    max-width: 800px;
    background: #fff;
    padding: 20px;
}

.settings-content {
    width: 400px;
    max-width: 100%;
    box-sizing: border-box;
}

.form-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    max-width: 400px;
    box-sizing: border-box;
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
