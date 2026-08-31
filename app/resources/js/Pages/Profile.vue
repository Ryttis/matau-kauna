<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { ROLE_REPORTER, isStaffRole } from '@/constants/domain/roles'
import { SETTINGS_PAGE_STYLE } from '@/constants/ui/theme'
import { formatDateTimeLt } from '@/utils/dateFormat'
import '@/Components/App/settings-profile-page.css'

const page = usePage()
const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
const user = computed(() => page.props.auth?.user ?? null)
const authRoles = computed(() => page.props.auth?.roles ?? [])
const isViispUser = computed(() => !!user.value?.viisp_personal_code_hash)
const isReporterOnly = computed(
    () =>
        authRoles.value.some((r) => r.role === ROLE_REPORTER) &&
        !authRoles.value.some((r) => isStaffRole(r.role)),
)
const viispNeedsFirstPassword = computed(
    () => isViispUser.value && !user.value?.viisp_local_password_set_at,
)
const showCurrentPasswordField = computed(() => !viispNeedsFirstPassword.value && !isReporterOnly.value)
const passwordSectionTitle = computed(() =>
    viispNeedsFirstPassword.value ? 'Nustatyti slaptažodį' : 'Keisti slaptažodį',
)
const passwordSubmitLabel = computed(() =>
    viispNeedsFirstPassword.value ? 'Sukurti slaptažodį' : 'Patvirtinti',
)

const roleDisplayNames = computed(() =>
    (authRoles.value ?? []).map((r) => r?.name).filter(Boolean)
)

function isRoleDisplayName(value) {
    if (!value || typeof value !== 'string') return false
    return roleDisplayNames.value.some((rn) => rn && String(rn).trim() === value.trim())
}

const profileForm = useForm({
    name: '',
    email: '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const sections = ref({ profile: true, password: true, terms: true })
const showTermsModal = ref(false)

const termsAcceptedAt = computed(() => user.value?.terms_accepted_at ?? null)
const termsVersion = computed(() => user.value?.accepted_terms_version ?? null)
const termsAcceptedAtFormatted = computed(() => {
    if (!termsAcceptedAt.value) return null
    return formatDateTimeLt(termsAcceptedAt.value) || null
})
const termsBodyPlain = computed(() => {
    const body = termsVersion.value?.body
    if (!body) return null
    return body.replace(/<[^>]*>/g, '').trim()
})

function initProfileForm() {
    const u = user.value
    if (!u) return
    const namePart = isRoleDisplayName(u.name) ? '' : (u.name || '').trim()
    const surnamePart = isRoleDisplayName(u.surname) ? '' : (u.surname || '').trim()
    profileForm.name = [namePart, surnamePart].filter(Boolean).join(' ').trim()
    profileForm.email = u.email || ''
}

watch(user, initProfileForm, { immediate: true })

const toggleSection = (key) => {
    sections.value[key] = !sections.value[key]
}

const updateProfile = () => {
    profileForm
        .transform((data) => ({
            name: (data.name || '').trim(),
            email: (data.email || '').trim(),
        }))
        .patch(route('settings.profile'), { preserveScroll: true })
}

const passwordManagerUsername = computed(() => user.value?.email ?? '')

const updatePassword = () => {
    passwordForm.put(route('settings.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <Head title="Profilis" />
    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="settings-container settings-page" :style="SETTINGS_PAGE_STYLE">
            <div class="settings-content">
                <div class="settings-inner settings-page-column">
                    <SettingsSection
                        title="Profilio duomenys"
                        :open="sections.profile"
                        @toggle="toggleSection('profile')"
                    >
                        <form @submit.prevent="updateProfile" class="form-grid">
                            <AppInput
                                v-model="profileForm.name"
                                placeholder="Vardas ir pavardė"
                                :show-icon="true"
                                autocomplete="name"
                                readonly
                            />
                            <div v-if="isViispUser" class="form-hint">
                                Vardas ir pavardė atnaujinami automatiškai pagal el. valdžios vartų duomenis ir negali būti keičiami.
                            </div>
                            <AppInput
                                v-model="profileForm.email"
                                type="email"
                                placeholder="El. paštas"
                                :show-icon="true"
                                autocomplete="email"
                            />
                            <AppButton
                                text="Saugoti"
                                variant="primary"
                                :disabled="profileForm.processing"
                            />
                        </form>
                    </SettingsSection>

                    <SettingsSection
                        :title="passwordSectionTitle"
                        :open="sections.password"
                        @toggle="toggleSection('password')"
                    >
                        <form @submit.prevent="updatePassword" class="form-grid form-grid--password">
                            <label class="password-form-sr-only" for="profile-password-username"
                                >Paskyros el. paštas (slaptažodžių tvarkyklei)</label
                            >
                            <input
                                id="profile-password-username"
                                type="text"
                                name="username"
                                autocomplete="username"
                                :value="passwordManagerUsername"
                                readonly
                                tabindex="-1"
                                class="password-form-username-field"
                            />
                            <p v-if="viispNeedsFirstPassword" class="form-hint">
                                Kad galėtumėte prisijungti el. paštu ir slaptažodžiu, sukurkite slaptažodį. El. valdžios vartais
                                prisijungti galėsite ir toliau.
                            </p>
                            <AppInput
                                v-if="showCurrentPasswordField"
                                v-model="passwordForm.current_password"
                                type="password"
                                placeholder="Esamas slaptažodis"
                                :show-icon="true"
                                autocomplete="current-password"
                            />
                            <AppInput
                                v-model="passwordForm.password"
                                type="password"
                                placeholder="Naujas slaptažodis"
                                :show-icon="true"
                                autocomplete="new-password"
                            />
                            <AppInput
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                placeholder="Pakartoti slaptažodį"
                                :show-icon="true"
                                autocomplete="new-password"
                            />
                            <div v-if="passwordForm.errors.password" class="form-error">
                                {{ passwordForm.errors.password }}
                            </div>
                            <AppButton
                                :text="passwordSubmitLabel"
                                variant="primary"
                                :disabled="passwordForm.processing"
                            />
                        </form>
                    </SettingsSection>

                    <SettingsSection
                        v-if="termsAcceptedAt"
                        title="Taisyklių sutikimas"
                        :open="sections.terms"
                        @toggle="toggleSection('terms')"
                    >
                        <div class="terms-card">
                            <div v-if="termsAcceptedAtFormatted" class="terms-row">
                                <span class="terms-label">Sutikimo data</span>
                                <span class="terms-value">{{ termsAcceptedAtFormatted }}</span>
                            </div>
                            <div v-if="termsVersion" class="terms-row">
                                <span class="terms-label">Versija</span>
                                <span class="terms-value">{{ termsVersion.version }}</span>
                            </div>
                            <button
                                v-if="termsBodyPlain"
                                type="button"
                                class="terms-view-btn"
                                @click="showTermsModal = true"
                            >
                                Peržiūrėti taisykles
                            </button>
                        </div>
                    </SettingsSection>
                </div>
            </div>
        </div>

        <div v-if="showTermsModal" class="terms-modal-backdrop" @click.self="showTermsModal = false">
            <div class="terms-modal">
                <div class="terms-modal__header">
                    <h3>{{ termsVersion?.title || 'Taisyklės' }}</h3>
                    <button type="button" class="terms-modal__close" @click="showTermsModal = false">×</button>
                </div>
                <div v-if="termsVersion" class="terms-modal__meta">
                    Versija {{ termsVersion.version }}<span v-if="termsAcceptedAtFormatted"> · sutikta {{ termsAcceptedAtFormatted }}</span>
                </div>
                <div class="terms-modal__body">{{ termsBodyPlain }}</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.terms-card {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.terms-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
}
.terms-label {
    color: #666;
    font-size: 14px;
}
.terms-value {
    color: #1a1a1a;
    font-weight: 600;
    font-size: 14px;
}
.terms-view-btn {
    margin-top: 8px;
    padding: 10px 0;
    background: transparent;
    border: none;
    border-top: 1px solid #e5e5e5;
    color: var(--color-primary, #1946A0);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    text-align: left;
}
.terms-view-btn:hover {
    text-decoration: underline;
}
.terms-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}
.terms-modal {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    width: 100%;
    max-width: 600px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}
.terms-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}
.terms-modal__header h3 {
    margin: 0;
    font-size: 18px;
    color: #1a1a1a;
}
.terms-modal__close {
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    color: #666;
    padding: 4px 8px;
}
.terms-modal__meta {
    color: #666;
    font-size: 12px;
    margin-bottom: 12px;
}
.terms-modal__body {
    overflow-y: auto;
    color: #333;
    font-size: 14px;
    line-height: 1.5;
    white-space: pre-wrap;
}
</style>
