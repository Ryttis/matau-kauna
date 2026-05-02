<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { SETTINGS_PAGE_STYLE } from '@/constants/ui/theme'
import '@/Components/App/settings-profile-page.css'

const page = usePage()
const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
const user = computed(() => page.props.auth?.user ?? null)
const authRoles = computed(() => page.props.auth?.roles ?? [])
const isViispUser = computed(() => !!user.value?.viisp_personal_code_hash)
const viispNeedsFirstPassword = computed(
    () => isViispUser.value && !user.value?.viisp_local_password_set_at,
)
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

const sections = ref({ profile: true, password: true })

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
                                v-if="!viispNeedsFirstPassword"
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
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

