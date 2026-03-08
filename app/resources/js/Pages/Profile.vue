<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    SETTINGS_CONTAINER_MAX,
    SETTINGS_INNER_MAX,
    SETTINGS_BUTTON_WIDTH,
    SETTINGS_BUTTON_HEIGHT,
    SETTINGS_BORDER_RADIUS,
    SETTINGS_INPUT_RADIUS,
} from '@/constants/theme'

const settingsPageStyle = {
    '--settings-container-max': `${SETTINGS_CONTAINER_MAX}px`,
    '--settings-inner-max': `${SETTINGS_INNER_MAX}px`,
    '--settings-button-width': `${SETTINGS_BUTTON_WIDTH}px`,
    '--settings-button-height': `${SETTINGS_BUTTON_HEIGHT}px`,
    '--settings-radius': `${SETTINGS_BORDER_RADIUS}px`,
    '--settings-input-radius': `${SETTINGS_INPUT_RADIUS}px`,
}

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const authRoles = computed(() => page.props.auth?.roles ?? [])

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
        <div class="settings-container settings-page" :style="settingsPageStyle">
            <div class="settings-content">
                <div class="settings-inner">
                    <SettingsSection
                        title="Profilio duomenys"
                        :open="sections.profile"
                        @toggle="toggleSection('profile')"
                    >
                        <form @submit.prevent="updateProfile" class="form-grid">
                            <AppInput v-model="profileForm.name" placeholder="Vardas" :show-icon="true" />
                            <AppInput v-model="profileForm.surname" placeholder="Pavardė" :show-icon="true" />
                            <AppInput v-model="profileForm.email" type="email" placeholder="El. paštas" :show-icon="true" />
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
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
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

.form-error {
    font-size: 14px;
    color: var(--color-error, #c62828);
}
</style>
