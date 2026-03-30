<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { SETTINGS_PAGE_STYLE } from '@/constants/ui/theme'

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const authRoles = computed(() => page.props.auth?.roles ?? [])
const isViispUser = computed(() => !!user.value?.viisp_personal_code_hash)

const roleDisplayNames = computed(() =>
    (authRoles.value ?? []).map((r) => r?.name).filter(Boolean)
)

function isRoleDisplayName(value) {
    if (!value || typeof value !== 'string') return false
    return roleDisplayNames.value.some((rn) => rn && String(rn).trim() === value.trim())
}

const profileForm = useForm({
    name: '',
    surname: '',
    email: '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const sections = ref({ profile: true, password: true })

function initProfileForm() {
    const u = user.value
    if (!u) return
    profileForm.name = isRoleDisplayName(u.name) ? '' : (u.name || '')
    profileForm.surname = isRoleDisplayName(u.surname) ? '' : (u.surname || '')
    profileForm.email = u.email || ''
}

watch(user, initProfileForm, { immediate: true })

const toggleSection = (key) => {
    sections.value[key] = !sections.value[key]
}

const updateProfile = () => {
    profileForm.patch(route('settings.profile'), { preserveScroll: true })
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
        <div class="settings-container settings-page" :style="SETTINGS_PAGE_STYLE">
            <div class="settings-content">
                <div class="settings-inner">
                    <SettingsSection
                        title="Profilio duomenys"
                        :open="sections.profile"
                        @toggle="toggleSection('profile')"
                    >
                        <form @submit.prevent="updateProfile" class="form-grid">
                            <AppInput
                                v-model="profileForm.name"
                                placeholder="Vardas"
                                :show-icon="true"
                                autocomplete="given-name"
                                :readonly="isViispUser"
                            />
                            <AppInput
                                v-model="profileForm.surname"
                                placeholder="Pavardė"
                                :show-icon="true"
                                autocomplete="family-name"
                                :readonly="isViispUser"
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
                        title="Keisti slaptažodį"
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
                            <AppInput
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
                                text="Patvirtinti"
                                variant="primary"
                                :disabled="passwordForm.processing"
                            />
                        </form>
                    </SettingsSection>
                </div>
            </div>
        </div>
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

.form-error {
    font-size: 14px;
    color: var(--color-error, #c62828);
}

.form-hint {
    font-size: 14px;
    color: #555;
    line-height: 1.4;
}

.form-grid--password {
    position: relative;
}

.password-form-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.password-form-username-field {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
    opacity: 0;
    pointer-events: none;
}
</style>
