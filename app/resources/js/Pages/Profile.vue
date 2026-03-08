<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SettingsSection from '@/Components/App/SettingsSection.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const authRoles = computed(() => page.props.auth?.roles ?? [])

const roleDisplayNames = (page.props.auth?.roles ?? [])
    .map((r) => r?.name)
    .filter(Boolean)

function isRoleDisplayName(value) {
    if (!value || typeof value !== 'string') return false
    return roleDisplayNames.some((rn) => rn && String(rn).trim() === value.trim())
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
onMounted(initProfileForm)

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
        <div class="settings-container settings-page">
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
    max-width: var(--settings-container-max, 640px);
    margin: 0 auto;
    padding: 0 24px;
}
.settings-content {
    width: 100%;
}
.settings-inner {
    max-width: var(--settings-inner-max, 400px);
    width: 100%;
}
.form-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.form-error {
    font-size: 14px;
    color: var(--color-error, #c62828);
}
</style>
