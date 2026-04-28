<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import SettingsPrivacyBlock from '@/Components/App/SettingsPrivacyBlock.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import DeleteAccountModal from '@/Components/App/DeleteAccountModal.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'
import { ROLE_INSTITUTION_ADMIN, ROLE_REPORTER } from '@/constants/domain/roles'
import { SETTINGS_PAGE_STYLE } from '@/constants/ui/theme'
import { useAuth } from '@/composables/useAuth'
import '@/Components/App/settings-profile-page.css'

const {
    user,
    permissions,
    hasAssignedRole,
    isSystemAdmin,
    isProblemAdmin,
} = useAuth()

const isInstitutionAdmin = computed(() => hasAssignedRole(ROLE_INSTITUTION_ADMIN))

const isReporterOnly = computed(
    () => hasAssignedRole(ROLE_REPORTER) && !permissions.value.includes('issues.view')
)

const props = defineProps({
    institution: {
        type: Object,
        default: null,
    },
    institutionGis: {
        type: Object,
        default: () => ({}),
    },
})

const institutionForm = useForm({
    name: '',
    code: '',
    gis: {
        enabled: false,
        generate_token_url: '',
        technical_username: '',
        technical_password: '',
        client_type: 'referer',
        token_expiration_minutes: 60,
        feature_server_url: '',
        attachment_url: '',
        wkid: 3346,
    },
})

const showDeleteModal = ref(false)
const deleteLoading = ref(false)
const twoFactorEnabled = ref(false)
const emailUpdatesInstitutionAdmin = ref(false)
const emailNotificationsEnabled = ref(false)
const reporterEmailOwnProblems = ref(true)
const reporterEmailWatchedProblems = ref(true)

const sections = ref({ institution: true })

function initFromUser() {
    const u = user.value
    if (!u) return
    institutionForm.name = props.institution?.name ?? u.institution?.name ?? ''
    institutionForm.code = props.institution?.code ?? u.institution?.code ?? ''
    institutionForm.gis.enabled = !!props.institutionGis?.enabled
    institutionForm.gis.generate_token_url = props.institutionGis?.generate_token_url ?? ''
    institutionForm.gis.technical_username = props.institutionGis?.technical_username ?? ''
    institutionForm.gis.technical_password = ''
    institutionForm.gis.client_type = props.institutionGis?.client_type ?? 'referer'
    institutionForm.gis.token_expiration_minutes = props.institutionGis?.token_expiration_minutes ?? 60
    institutionForm.gis.feature_server_url = props.institutionGis?.feature_server_url ?? ''
    institutionForm.gis.attachment_url = props.institutionGis?.attachment_url ?? ''
    institutionForm.gis.wkid = props.institutionGis?.wkid ?? 3346
    twoFactorEnabled.value = !!u.two_factor_enabled
    emailUpdatesInstitutionAdmin.value = !!u.email_updates_institution_admin
    emailNotificationsEnabled.value = !!u.email_notifications_enabled
    reporterEmailOwnProblems.value = u.reporter_email_own_problems !== false
    reporterEmailWatchedProblems.value = u.reporter_email_watched_problems !== false
}

watch([user, () => props.institutionGis, () => props.institution], initFromUser, { immediate: true })
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
    deleteLoading.value = true
    router.delete(route('settings.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
        },
        onFinish: () => {
            deleteLoading.value = false
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

const saveReporterEmailOwn = (value) => {
    const v = value !== undefined ? value : reporterEmailOwnProblems.value
    reporterEmailOwnProblems.value = v
    router.patch(route('settings.privacy'), {
        reporter_email_own_problems: v,
    }, { preserveScroll: true })
}

const saveReporterEmailWatched = (value) => {
    const v = value !== undefined ? value : reporterEmailWatchedProblems.value
    reporterEmailWatchedProblems.value = v
    router.patch(route('settings.privacy'), {
        reporter_email_watched_problems: v,
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
                        <p v-if="institutionForm.errors.name" class="form-error">{{ institutionForm.errors.name }}</p>
                        <AppInput v-model="institutionForm.code" placeholder="Kodas" :showIcon="true" />
                        <p v-if="institutionForm.errors.code" class="form-error">{{ institutionForm.errors.code }}</p>
                        <div class="gis-toggle-row">
                            <span class="gis-toggle-label">Enable GIS send</span>
                            <AppSwitch v-model="institutionForm.gis.enabled" />
                        </div>
                        <p v-if="institutionForm.errors['gis.enabled']" class="form-error">{{ institutionForm.errors['gis.enabled'] }}</p>

                        <div v-if="institutionForm.gis.enabled" class="gis-fields">
                            <AppInput
                                v-model="institutionForm.gis.generate_token_url"
                                label="GenerateTokenUrl"
                                placeholder="https://digital.kaunas.lt/portal/sharing/rest/generateToken"
                            />
                            <p v-if="institutionForm.errors['gis.generate_token_url']" class="form-error">{{ institutionForm.errors['gis.generate_token_url'] }}</p>

                            <AppInput
                                v-model="institutionForm.gis.technical_username"
                                label="Techninio vartotojo vardas"
                                placeholder="Techninis vartotojas"
                            />
                            <p v-if="institutionForm.errors['gis.technical_username']" class="form-error">{{ institutionForm.errors['gis.technical_username'] }}</p>

                            <AppInput
                                v-model="institutionForm.gis.technical_password"
                                type="password"
                                label="Slaptažodis"
                                :placeholder="props.institutionGis?.has_technical_password ? 'Palikite tuščią, jei nekeičiate' : 'Slaptažodis'"
                                autocomplete="new-password"
                            />
                            <p v-if="institutionForm.errors['gis.technical_password']" class="form-error">{{ institutionForm.errors['gis.technical_password'] }}</p>

                            <AppInput
                                v-model="institutionForm.gis.client_type"
                                label="Kliento tipas"
                                placeholder="referer"
                            />
                            <p v-if="institutionForm.errors['gis.client_type']" class="form-error">{{ institutionForm.errors['gis.client_type'] }}</p>

                            <AppInput
                                v-model="institutionForm.gis.token_expiration_minutes"
                                type="number"
                                label="Token galiojimas (min.)"
                                placeholder="60"
                            />
                            <p v-if="institutionForm.errors['gis.token_expiration_minutes']" class="form-error">{{ institutionForm.errors['gis.token_expiration_minutes'] }}</p>

                            <AppInput
                                v-model="institutionForm.gis.feature_server_url"
                                label="FeatureServer Url"
                                placeholder="https://.../FeatureServer/0"
                            />
                            <p v-if="institutionForm.errors['gis.feature_server_url']" class="form-error">{{ institutionForm.errors['gis.feature_server_url'] }}</p>

                            <AppInput
                                v-model="institutionForm.gis.attachment_url"
                                label="AttachmentUrl"
                                placeholder="https://.../FeatureServer/0"
                            />
                            <p v-if="institutionForm.errors['gis.attachment_url']" class="form-error">{{ institutionForm.errors['gis.attachment_url'] }}</p>

                            <label class="gis-select-row">
                                <span>Koordinačių sistema (WKID)</span>
                                <select class="gis-select" v-model.number="institutionForm.gis.wkid">
                                    <option :value="3346">LKS-94 (3346)</option>
                                    <option :value="4326">WGS84 (4326)</option>
                                    <option :value="3857">Web Mercator (3857)</option>
                                </select>
                            </label>
                            <p v-if="institutionForm.errors['gis.wkid']" class="form-error">{{ institutionForm.errors['gis.wkid'] }}</p>

                            <p v-if="institutionForm.errors.gis" class="form-error">{{ institutionForm.errors.gis }}</p>
                            <div class="gis-status-card">
                                <p class="gis-status-text">
                                    Aktyvus tokenas:
                                    {{ props.institutionGis?.has_active_token ? 'yra' : 'nėra' }}
                                </p>
                                <p v-if="props.institutionGis?.active_token_expires_at" class="gis-status-text">
                                    Galioja iki: {{ props.institutionGis.active_token_expires_at }}
                                </p>
                            </div>
                        </div>
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
                    :reporter-email-own-problems="reporterEmailOwnProblems"
                    :reporter-email-watched-problems="reporterEmailWatchedProblems"
                    :is-institution-admin="isInstitutionAdmin"
                    :is-system-admin="isSystemAdmin"
                    :is-problem-admin="isProblemAdmin"
                    :is-reporter-only="isReporterOnly"
                    @update:twoFactorEnabled="twoFactorEnabled = $event"
                    @update:emailUpdatesInstitutionAdmin="emailUpdatesInstitutionAdmin = $event"
                    @update:emailNotificationsEnabled="emailNotificationsEnabled = $event"
                    @update:reporterEmailOwnProblems="reporterEmailOwnProblems = $event"
                    @update:reporterEmailWatchedProblems="reporterEmailWatchedProblems = $event"
                    @save-email-updates-admin="(val) => saveEmailUpdatesInstitutionAdmin(val)"
                    @save-email-notifications="(val) => saveEmailNotificationsEnabled(val)"
                    @save-reporter-email-own="(val) => saveReporterEmailOwn(val)"
                    @save-reporter-email-watched="(val) => saveReporterEmailWatched(val)"
                    @delete-account="showDeleteModal = true"
                />
                </div>
            </div>
        </div>

        <DeleteAccountModal
            :open="showDeleteModal"
            :confirm-loading="deleteLoading"
            @close="showDeleteModal = false"
            @confirm="deleteAccount"
        />

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

.gis-toggle-row {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 8px 0;
}

.gis-toggle-label {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text);
}

.gis-fields {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.gis-status-card {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--color-primary, #1946A0) 6%, white);
    border: 1px solid color-mix(in srgb, var(--color-primary, #1946A0) 16%, white);
    box-sizing: border-box;
}

.gis-status-text {
    margin: 0;
    font-size: 14px;
    color: var(--color-text);
}

.gis-select-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 14px;
}

.gis-select {
    padding: 8px 10px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 14px;
    background: var(--color-white);
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
    font-family: var(--font-family-base, 'Open Sans', sans-serif) !important;
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
