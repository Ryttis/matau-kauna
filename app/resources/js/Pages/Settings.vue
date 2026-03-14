<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import SettingsPrivacyBlock from '@/Components/App/SettingsPrivacyBlock.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import DeleteAccountModal from '@/Components/App/DeleteAccountModal.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'
import { ROLE_SYSTEM_ADMIN, ROLE_INSTITUTION_ADMIN, ROLE_PROBLEM_ADMIN } from '@/constants/roles'
import { SETTINGS_PAGE_STYLE } from '@/constants/theme'

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const authRoles = computed(() => page.props.auth?.roles || [])
const activeRole = computed(() => {
    const auth = page.props.auth
    const roles = authRoles.value
    return auth?.activeRole ?? auth?.active_role ?? (roles?.length === 1 ? roles[0] : null)
})
const isSystemAdmin = computed(() => (activeRole.value?.role ?? '') === ROLE_SYSTEM_ADMIN)
const isProblemAdmin = computed(() => (activeRole.value?.role ?? '') === ROLE_PROBLEM_ADMIN)

const isInstitutionAdmin = computed(() =>
    authRoles.value.some((r) => r.role === ROLE_INSTITUTION_ADMIN)
)

const institutionForm = useForm({
    name: '',
    code: '',
})

const deleteAccountForm = useForm({
    password: '',
})

const showDeleteModal = ref(false)
const twoFactorEnabled = ref(false)
const emailUpdatesInstitutionAdmin = ref(false)
const emailNotificationsEnabled = ref(false)

const sections = ref({ institution: true })

function initFromUser() {
    const u = user.value
    if (!u) return
    institutionForm.name = u.institution?.name ?? ''
    institutionForm.code = u.institution?.code ?? ''
    twoFactorEnabled.value = !!u.two_factor_enabled
    emailUpdatesInstitutionAdmin.value = !!u.email_updates_institution_admin
    emailNotificationsEnabled.value = !!u.email_notifications_enabled
}

watch(user, initFromUser, { immediate: true })
onMounted(initFromUser)

const toggleSection = (section) => {
    sections.value[section] = !sections.value[section]
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

const saveEmailUpdatesInstitutionAdmin = (value) => {
    const v = value !== undefined ? value : emailUpdatesInstitutionAdmin.value
    emailUpdatesInstitutionAdmin.value = v
    router.patch(route('settings.privacy'), {
        email_updates_institution_admin: v,
    }, { preserveScroll: true })
}

const saveEmailNotificationsEnabled = (value) => {
    const v = value !== undefined ? value : emailNotificationsEnabled.value
    emailNotificationsEnabled.value = v
    router.patch(route('settings.privacy'), {
        email_notifications_enabled: v,
    }, { preserveScroll: true })
}

</script>

<template>
    <Head title="Nustatymai" />

    <AuthenticatedLayout>
        <div class="settings-container settings-page" :style="SETTINGS_PAGE_STYLE">
            <div class="settings-content">
                <div class="settings-inner">
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
                    :is-problem-admin="isProblemAdmin"
                    @update:twoFactorEnabled="twoFactorEnabled = $event"
                    @update:emailUpdatesInstitutionAdmin="emailUpdatesInstitutionAdmin = $event"
                    @update:emailNotificationsEnabled="emailNotificationsEnabled = $event"
                    @save-email-updates-admin="(val) => saveEmailUpdatesInstitutionAdmin(val)"
                    @save-email-notifications="(val) => saveEmailNotificationsEnabled(val)"
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

    </AuthenticatedLayout>
</template>

<style scoped>
.settings-container {
    width: 100%;
    max-width: var(--settings-container-max);
    margin: 0;
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
    margin: 0;
    box-sizing: border-box;
}

.form-error {
    color: var(--color-error);
    font-size: 14px;
    margin-bottom: 0;
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
    align-self: flex-start !important;
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
    width: 15px !important;
    height: 15px !important;
}

</style>
