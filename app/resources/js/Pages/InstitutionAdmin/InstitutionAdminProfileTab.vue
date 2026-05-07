<script setup>
import { ref, watch, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'
import { INSTITUTION_TYPES } from '@/constants/domain/institutionTypes'
import { useSuccessModal } from '@/composables/useSuccessModal'

const GIS_FIELD_MAPPING_DEFAULTS = {
    status: 'busena',
    category: 'pranesimo_tipas',
    created_at: 'registravimo_data',
    completion_deadline: 'vykdymo_terminas',
    address: 'adresas',
    reporter: 'pranese',
    internal_number: '',
    description: '',
}

const GIS_FIELD_MAPPING_ROWS = [
    { key: 'status', label: 'Būsena', placeholder: 'busena' },
    { key: 'category', label: 'Kategorija / tipas', placeholder: 'pranesimo_tipas' },
    { key: 'created_at', label: 'Registravimo data', placeholder: 'registravimo_data' },
    { key: 'completion_deadline', label: 'Vykdymo terminas', placeholder: 'vykdymo_terminas' },
    { key: 'address', label: 'Adresas', placeholder: 'adresas' },
    { key: 'reporter', label: 'Pranešėjas', placeholder: 'pranese' },
    { key: 'internal_number', label: 'Vidinis numeris', placeholder: 'Pvz. vidinis_nr' },
    { key: 'description', label: 'Aprašymas', placeholder: 'Pvz. aprasymas' },
]

function defaultFieldMapping() {
    return { ...GIS_FIELD_MAPPING_DEFAULTS }
}

const props = defineProps({
    profileSaveMode: {
        type: String,
        required: true,
        validator: (v) => v === 'admin' || v === 'settings',
    },
    institutionRecord: {
        type: Object,
        required: true,
    },
    institutionGis: {
        type: Object,
        default: () => ({}),
    },
    activeInstitutionId: {
        type: Number,
        required: true,
    },
})

const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const settingsForm = useForm({
    name: '',
    code: '',
    type: '',
    contact_phone: '',
    contact_email: '',
    contact_address: '',
})

const adminSaving = ref(false)
const adminError = ref('')

function gisFromPayload(source) {
    const base = {
        enabled: false,
        generate_token_url: '',
        technical_username: '',
        technical_password: '',
        client_type: 'referer',
        token_expiration_minutes: 60,
        feature_server_url: '',
        attachment_url: '',
        wkid: 3346,
        use_field_mapping: false,
        field_mapping: defaultFieldMapping(),
        has_technical_password: false,
        has_active_token: false,
        active_token_expires_at: null,
    }
    if (!source) return base
    return {
        ...base,
        enabled: Boolean(source.enabled),
        generate_token_url: source.generate_token_url ?? '',
        technical_username: source.technical_username ?? '',
        technical_password: '',
        has_technical_password: Boolean(source.has_technical_password),
        client_type: source.client_type || 'referer',
        token_expiration_minutes: source.token_expiration_minutes ?? 60,
        feature_server_url: source.feature_server_url ?? '',
        attachment_url: source.attachment_url ?? '',
        wkid: source.wkid ?? 3346,
        use_field_mapping: Boolean(source.use_field_mapping),
        field_mapping: { ...defaultFieldMapping(), ...(source.field_mapping ?? {}) },
        has_active_token: Boolean(source.has_active_token),
        active_token_expires_at: source.active_token_expires_at ?? null,
    }
}

function gisSubmitPayload(gis) {
    const payload = {
        enabled: Boolean(gis?.enabled),
        generate_token_url: gis?.generate_token_url || null,
        technical_username: gis?.technical_username || null,
        client_type: gis?.client_type || null,
        token_expiration_minutes: gis?.token_expiration_minutes ? Number(gis.token_expiration_minutes) : null,
        feature_server_url: gis?.feature_server_url || null,
        attachment_url: gis?.attachment_url || null,
        wkid: gis?.wkid ? Number(gis.wkid) : 3346,
        use_field_mapping: Boolean(gis?.use_field_mapping),
    }
    if (payload.use_field_mapping) {
        payload.field_mapping = { ...defaultFieldMapping(), ...(gis?.field_mapping ?? {}) }
    }
    const password = (gis?.technical_password ?? '').trim()
    if (password !== '') {
        payload.technical_password = password
    }
    return payload
}

const adminForm = ref({
    name: '',
    code: '',
    type: '',
    contact_phone: '',
    contact_email: '',
    contact_address: '',
    integration_dvs: '',
    is_active: true,
    is_email_only: false,
    gis: gisFromPayload(),
})

function syncSettingsForm() {
    const inst = props.institutionRecord
    settingsForm.name = inst?.name ?? ''
    settingsForm.code = inst?.code ?? ''
    settingsForm.type = inst?.type ?? ''
    settingsForm.contact_phone = inst?.contact_phone ?? ''
    settingsForm.contact_email = inst?.contact_email ?? ''
    settingsForm.contact_address = inst?.contact_address ?? ''
}

function syncAdminForm() {
    const inst = props.institutionRecord
    adminForm.value = {
        name: inst?.name ?? '',
        code: inst?.code ?? '',
        type: inst?.type ?? '',
        contact_phone: inst?.contact_phone ?? '',
        contact_email: inst?.contact_email ?? '',
        contact_address: inst?.contact_address ?? '',
        integration_dvs: inst?.integration_dvs ?? '',
        is_active: inst?.is_active ?? true,
        is_email_only: inst?.is_email_only ?? false,
        gis: gisFromPayload(props.institutionGis),
    }
}

watch(
    () => [props.institutionRecord, props.institutionGis, props.profileSaveMode],
    () => {
        if (props.profileSaveMode === 'settings') {
            syncSettingsForm()
        } else {
            syncAdminForm()
        }
    },
    { deep: true },
)

onMounted(() => {
    if (props.profileSaveMode === 'settings') {
        syncSettingsForm()
    } else {
        syncAdminForm()
    }
})

function submitSettings() {
    settingsForm
        .transform((data) => ({
            name: data.name,
            code: data.code || null,
            type: data.type || null,
            contact_phone: data.contact_phone || null,
            contact_email: data.contact_email || null,
            contact_address: data.contact_address || null,
        }))
        .patch(route('institution-admin.profile.update'), {
            preserveScroll: true,
            onSuccess: () => showWithMessage('Įstaigos duomenys atnaujinti.'),
        })
}

async function submitAdmin() {
    if (adminSaving.value) return
    adminSaving.value = true
    adminError.value = ''
    try {
        const f = adminForm.value
        await axios.put(adminApi.institution(props.activeInstitutionId), {
            name: f.name,
            code: f.code || null,
            type: f.type || null,
            contact_phone: f.contact_phone || null,
            contact_email: f.contact_email || null,
            contact_address: f.contact_address || null,
            integration_email: f.contact_email || null,
            integration_dvs: f.integration_dvs || null,
            is_active: f.is_active,
            is_email_only: f.is_email_only ?? false,
            gis: gisSubmitPayload(f.gis),
        })
        showWithMessage('Įstaigos duomenys atnaujinti.')
        router.reload({ only: ['institutionRecord', 'institutionGis'] })
    } catch (e) {
        adminError.value = e.response?.data?.message ?? 'Nepavyko išsaugoti.'
    } finally {
        adminSaving.value = false
    }
}
</script>

<template>
    <div class="inst-profile-tab admin-tab">
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />

        <div class="admin-tab-intro">
            <h3 class="admin-tab-intro__title">Įstaigos profilis</h3>
            <p class="admin-tab-intro__desc">
                {{ profileSaveMode === 'settings'
                    ? 'Tvarkykite pagrindinius įstaigos duomenis. GIS integraciją ir kitus papildomus nustatymus tvarko sistemos administratorius.'
                    : 'Tvarkykite įstaigos duomenis ir GIS integraciją.'
                }}
            </p>
        </div>

        <template v-if="profileSaveMode === 'settings'">
            <form class="form-grid" @submit.prevent="submitSettings">
                <div class="field full">
                    <AppInput v-model="settingsForm.name" label="Pavadinimas" required />
                    <p v-if="settingsForm.errors.name" class="form-error">{{ settingsForm.errors.name }}</p>
                </div>

                <div class="field">
                    <AppInput v-model="settingsForm.code" label="Kodas" />
                    <p v-if="settingsForm.errors.code" class="form-error">{{ settingsForm.errors.code }}</p>
                </div>

                <div class="field">
                    <AppSelect
                        v-model="settingsForm.type"
                        label="Tipas"
                        variant="white"
                        :placeholder="''"
                    >
                        <option v-for="t in INSTITUTION_TYPES" :key="t.value || 'empty'" :value="t.value">{{ t.label }}</option>
                    </AppSelect>
                    <p v-if="settingsForm.errors.type" class="form-error">{{ settingsForm.errors.type }}</p>
                </div>

                <div class="field">
                    <AppInput v-model="settingsForm.contact_phone" label="Telefonas" />
                    <p v-if="settingsForm.errors.contact_phone" class="form-error">{{ settingsForm.errors.contact_phone }}</p>
                </div>

                <div class="field">
                    <AppInput v-model="settingsForm.contact_email" label="El. paštas" type="email" />
                    <p v-if="settingsForm.errors.contact_email" class="form-error">{{ settingsForm.errors.contact_email }}</p>
                </div>

                <div class="field full">
                    <AppInput v-model="settingsForm.contact_address" label="Adresas" />
                    <p v-if="settingsForm.errors.contact_address" class="form-error">{{ settingsForm.errors.contact_address }}</p>
                </div>

                <div class="field full form-actions">
                    <AppButton
                        text="Saugoti"
                        variant="primary"
                        as-button
                        :disabled="settingsForm.processing"
                        :loading="settingsForm.processing"
                        @click="submitSettings"
                    />
                </div>
            </form>
        </template>

        <template v-else>
            <p v-if="adminError" class="error-banner">{{ adminError }}</p>
            <form class="form-grid" @submit.prevent="submitAdmin">
                <AppInput v-model="adminForm.name" label="Pavadinimas" required />
                <AppInput v-model="adminForm.code" label="Kodas" />
                <AppSelect
                    v-model="adminForm.type"
                    label="Tipas"
                    variant="white"
                    :placeholder="''"
                >
                    <option v-for="t in INSTITUTION_TYPES" :key="t.value || 'empty'" :value="t.value">{{ t.label }}</option>
                </AppSelect>
                <AppInput v-model="adminForm.contact_phone" label="Telefonas" />
                <AppInput v-model="adminForm.contact_email" label="Kontaktinis el. paštas" type="email" required />
                <AppInput v-model="adminForm.contact_address" label="Adresas" />
                <AppInput v-model="adminForm.integration_dvs" label="Integracija DVS" />

                <label class="checkbox-row">
                    <input v-model="adminForm.is_active" type="checkbox" />
                    <span>Įstaiga aktyvi</span>
                </label>
                <label class="checkbox-row">
                    <input v-model="adminForm.is_email_only" type="checkbox" />
                    <span>Tik el. paštas</span>
                </label>

                <div class="gis-toggle-row">
                    <span class="gis-toggle-label">GIS integracija</span>
                    <AppSwitch v-model="adminForm.gis.enabled" />
                </div>

                <div v-if="adminForm.gis.enabled" class="gis-fields">
                    <AppInput v-model="adminForm.gis.generate_token_url" label="GenerateTokenUrl" />
                    <AppInput v-model="adminForm.gis.technical_username" label="Techninio vartotojo vardas" />
                    <AppInput
                        v-model="adminForm.gis.technical_password"
                        type="password"
                        label="Slaptažodis"
                        :placeholder="adminForm.gis.has_technical_password ? 'Palikite tuščią, jei nekeičiate' : 'Slaptažodis'"
                    />
                    <AppInput v-model="adminForm.gis.client_type" label="Kliento tipas" />
                    <AppInput v-model="adminForm.gis.token_expiration_minutes" type="number" label="Token galiojimas (min.)" />
                    <AppInput v-model="adminForm.gis.feature_server_url" label="FeatureServer Url" />
                    <AppInput v-model="adminForm.gis.attachment_url" label="AttachmentUrl" />
                    <label class="gis-select-row">
                        <span>Koordinačių sistema (WKID)</span>
                        <select v-model.number="adminForm.gis.wkid" class="gis-select">
                            <option :value="3346">LKS-94 (3346)</option>
                            <option :value="4326">WGS84 (4326)</option>
                            <option :value="3857">Web Mercator (3857)</option>
                        </select>
                    </label>
                    <label class="checkbox-row gis-mapper-toggle">
                        <input v-model="adminForm.gis.use_field_mapping" type="checkbox" />
                        <span>Naudoti GIS laukų susiejimą</span>
                    </label>
                    <div v-if="adminForm.gis.use_field_mapping" class="gis-mapper">
                        <div
                            v-for="row in GIS_FIELD_MAPPING_ROWS"
                            :key="row.key"
                            class="gis-mapper-row"
                        >
                            <label :for="'admin-gis-map-' + row.key">{{ row.label }}</label>
                            <input
                                :id="'admin-gis-map-' + row.key"
                                v-model="adminForm.gis.field_mapping[row.key]"
                                class="gis-select"
                                :placeholder="row.placeholder"
                            />
                        </div>
                    </div>
                </div>

                <AppButton
                    text="Saugoti"
                    variant="primary"
                    as-button
                    :disabled="adminSaving"
                    :loading="adminSaving"
                    @click="submitAdmin"
                />
            </form>
        </template>
    </div>
</template>

<style scoped>
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 16px;
    width: 100%;
    max-width: min(100%, 40rem);
    align-items: start;
    box-sizing: border-box;
}

.form-grid .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.form-grid .field.full {
    grid-column: 1 / -1;
}

.form-grid .form-actions {
    margin-top: 8px;
}

.form-grid :deep(.app-input-group),
.form-grid :deep(.app-input-wrapper-edit),
.form-grid :deep(.app-input-edit),
.form-grid :deep(.app-select-group),
.form-grid :deep(.app-select-wrapper) {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.form-grid :deep(.app-input-edit) {
    background-color: #fff;
}

@media (max-width: 640px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-grid.form-grid--institution-profile-settings {
    width: 100%;
    max-width: calc(100% / 3);
    align-self: flex-start;
    box-sizing: border-box;
}

.form-error {
    color: #b91c1c;
    font-size: 13px;
    margin: -6px 0 0;
}
.gis-toggle-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.gis-toggle-label {
    font-size: 14px;
    font-weight: 600;
}
.gis-fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-left: 4px;
}
.gis-select-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 14px;
}
.gis-select {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 14px;
}
.gis-status-card {
    background: #f9fafb;
    border-radius: 8px;
    padding: 10px 12px;
}
.gis-status-text {
    margin: 0;
    font-size: 13px;
    color: #374151;
}
.error-banner {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}
.filter-label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}
.filter-select-wide {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    max-width: 100%;
}
.checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}
.gis-mapper-toggle {
    margin-top: 2px;
}
.gis-mapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 12px;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}
.gis-mapper-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}
.gis-mapper-row label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
}
</style>
