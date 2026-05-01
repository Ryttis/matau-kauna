<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsPrivacyBlock from '@/Components/App/SettingsPrivacyBlock.vue'
import DeleteAccountModal from '@/Components/App/DeleteAccountModal.vue'
import { Head, router } from '@inertiajs/vue3'
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
    () => hasAssignedRole(ROLE_REPORTER) && !permissions.value.includes('issues.view'),
)

const showDeleteModal = ref(false)
const deleteLoading = ref(false)
const twoFactorEnabled = ref(false)
const emailUpdatesInstitutionAdmin = ref(false)
const emailNotificationsEnabled = ref(false)
const reporterEmailOwnProblems = ref(true)
const reporterEmailWatchedProblems = ref(true)

function initFromUser() {
    const u = user.value
    if (!u) return
    twoFactorEnabled.value = !!u.two_factor_enabled
    emailUpdatesInstitutionAdmin.value = !!u.email_updates_institution_admin
    emailNotificationsEnabled.value = !!u.email_notifications_enabled
    reporterEmailOwnProblems.value = u.reporter_email_own_problems !== false
    reporterEmailWatchedProblems.value = u.reporter_email_watched_problems !== false
}

watch(user, initFromUser, { immediate: true })
onMounted(initFromUser)

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
                <div class="settings-inner settings-page-column">
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
</style>
