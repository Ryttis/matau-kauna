<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import AppInput from '@/Components/App/AppInput.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import ModalFooterActions from '@/Components/App/ModalFooterActions.vue'
import { adminApi } from '@/constants/api/adminApi'
import { INSTITUTION_TYPES } from '@/constants/domain/institutionTypes'

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

function emptyGisForm() {
    return {
        enabled: false,
        generate_token_url: '',
        technical_username: '',
        technical_password: '',
        has_technical_password: false,
        client_type: 'referer',
        token_expiration_minutes: 60,
        feature_server_url: '',
        attachment_url: '',
        wkid: 3346,
        use_field_mapping: false,
        field_mapping: defaultFieldMapping(),
        has_active_token: false,
        active_token_expires_at: null,
    }
}

function emptyInstitutionForm() {
    return {
        name: '',
        code: '',
        type: '',
        contact_phone: '',
        contact_email: '',
        contact_address: '',
        integration_email: '',
        integration_dvs: '',
        is_active: true,
        is_email_only: false,
        gis: emptyGisForm(),
    }
}

function gisFromPayload(source) {
    const base = emptyGisForm()
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

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const institutionsPage = ref(null)
const isCreateOpen = ref(false)
const isEditOpen = ref(false)
const createForm = ref(emptyInstitutionForm())
const editForm = ref(emptyInstitutionForm())
const showDeleteModal = ref(false)
const institutionToDelete = ref(null)
const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const deleteConfirmMessage = computed(() => {
    const inst = institutionToDelete.value
    if (!inst) return ''
    return 'Ar tikrai pašalinti įstaigą „' + inst.name + '"? Naudotojų įstaiga bus atimta, susijusios problemos liks be įstaigos.'
})

const loadInstitutions = async () => {
    const { data } = await axios.get(adminApi.institutions)
    institutionsPage.value = data
}

const openCreate = () => {
    createForm.value = emptyInstitutionForm()
    isCreateOpen.value = true
}

const openEdit = (inst) => {
    editForm.value = {
        id: inst.id,
        name: inst.name ?? '',
        code: inst.code ?? '',
        type: inst.type ?? '',
        contact_phone: inst.contact_phone ?? '',
        contact_email: inst.contact_email ?? '',
        contact_address: inst.contact_address ?? '',
        integration_email: inst.contact_email ?? inst.integration_email ?? '',
        integration_dvs: inst.integration_dvs ?? '',
        is_active: inst.is_active ?? true,
        is_email_only: inst.is_email_only ?? false,
        gis: gisFromPayload(inst.gis),
    }
    isEditOpen.value = true
}

const confirmDelete = (inst) => {
    institutionToDelete.value = inst
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    showDeleteModal.value = false
    institutionToDelete.value = null
}

const deleteInstitution = async () => {
    if (!institutionToDelete.value) return
    errorMessage.value = ''
    saving.value = true
    try {
        await axios.delete(adminApi.institution(institutionToDelete.value.id))
        closeDeleteModal()
        await loadInstitutions()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pašalinti įstaigos.'
    } finally {
        saving.value = false
    }
}

const createInstitution = async () => {
    errorMessage.value = ''
    saving.value = true
    try {
        const { gis, ...rest } = createForm.value
        await axios.post(adminApi.institutions, {
            ...rest,
            integration_email: createForm.value.contact_email || null,
            is_email_only: createForm.value.is_email_only ?? false,
            gis: gisSubmitPayload(gis),
        })
        isCreateOpen.value = false
        await loadInstitutions()
        showWithMessage('Įstaiga sukurta.')
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko sukurti įstaigos.'
    } finally {
        saving.value = false
    }
}

const updateInstitution = async () => {
    errorMessage.value = ''
    saving.value = true
    try {
        await axios.put(adminApi.institution(editForm.value.id), {
            name: editForm.value.name,
            code: editForm.value.code || null,
            type: editForm.value.type || null,
            contact_phone: editForm.value.contact_phone || null,
            contact_email: editForm.value.contact_email || null,
            contact_address: editForm.value.contact_address || null,
            integration_email: editForm.value.contact_email || null,
            integration_dvs: editForm.value.integration_dvs || null,
            is_active: editForm.value.is_active,
            is_email_only: editForm.value.is_email_only ?? false,
            gis: gisSubmitPayload(editForm.value.gis),
        })
        isEditOpen.value = false
        await loadInstitutions()
        showWithMessage('Įstaigos duomenys atnaujinti.')
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko išsaugoti įstaigos.'
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    loading.value = true
    try {
        await loadInstitutions()
    } catch {
        errorMessage.value = 'Nepavyko užkrauti įstaigų.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="admin-tab">
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="header-row">
            <h3 class="title">Įstaigos</h3>
            <AppButton text="+ Kurti įstaigą" variant="primary" as-button @click="openCreate" />
        </div>
        <p class="page-desc">Registruokite įstaigas, kurios tvarko problemas ir gauna pranešimus.</p>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else-if="institutionsPage?.data?.length">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pavadinimas</th>
                            <th>Kodas</th>
                            <th>Tipas</th>
                            <th>Kontaktai</th>
                            <th>Naudotojai</th>
                            <th>Būsena</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inst in institutionsPage.data" :key="inst.id">
                            <td>{{ inst.name }}</td>
                            <td>{{ inst.code ?? '-' }}</td>
                            <td>{{ inst.type ?? '-' }}</td>
                            <td>{{ inst.contact_email ?? inst.contact_phone ?? '-' }}</td>
                            <td>{{ inst.users_count ?? 0 }}</td>
                            <td>
                                <span :class="['pill', inst.is_active ? 'active' : 'inactive']">
                                    {{ inst.is_active ? 'Aktyvi' : 'Neaktyvi' }}
                                </span>
                                <span v-if="inst.is_email_only" class="pill email-only">Tik el. paštas</span>
                            </td>
                            <td class="actions">
                                <button type="button" class="link-btn" @click="openEdit(inst)">Redaguoti</button>
                                <span class="link-sep">|</span>
                                <button type="button" class="link-btn link-btn--danger" @click="confirmDelete(inst)">
                                    Trinti
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <p v-else class="empty">Nėra įstaigų. Sukurkite pirmąją.</p>

        <BaseModal
            :show="isCreateOpen"
            title="Kurti įstaigą"
            max-width="640px"
            @close="isCreateOpen = false"
        >
            <div class="form-grid">
                <div class="field full">
                    <AppInput v-model="createForm.name" label="Pavadinimas" required />
                </div>
                <AppInput v-model="createForm.code" label="Kodas" />
                <AppSelect
                    v-model="createForm.type"
                    label="Tipas"
                    variant="white"
                    :placeholder="''"
                >
                    <option v-for="opt in INSTITUTION_TYPES" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </AppSelect>
                <AppInput v-model="createForm.contact_phone" label="Telefonas" />
                <AppInput v-model="createForm.contact_email" label="El. paštas" type="email" required />
                <div class="field full">
                    <AppInput v-model="createForm.contact_address" label="Adresas" />
                </div>
                <div class="field full">
                    <AppInput v-model="createForm.integration_dvs" label="Integracija (DVS)" />
                </div>
                <div class="field full">
                    <div class="gis-row">
                        <span class="field-label">GIS integracija</span>
                        <label class="gis-toggle">
                            <input type="checkbox" v-model="createForm.gis.enabled" />
                            <span>Įjungti</span>
                        </label>
                    </div>
                    <template v-if="createForm.gis.enabled">
                        <AppInput v-model="createForm.gis.generate_token_url" label="Token generavimo URL" placeholder="https://..." />
                        <AppInput v-model="createForm.gis.technical_username" label="Techninis vartotojas" />
                        <AppInput
                            v-model="createForm.gis.technical_password"
                            type="password"
                            label="Slaptažodis"
                            autocomplete="new-password"
                        />
                        <AppInput v-model="createForm.gis.client_type" label="Kliento tipas" placeholder="pvz. referer" />
                        <AppInput
                            v-model="createForm.gis.token_expiration_minutes"
                            type="number"
                            label="Token galiojimas (min.)"
                        />
                        <AppInput v-model="createForm.gis.feature_server_url" label="FeatureServer URL" placeholder="https://..." />
                        <AppInput v-model="createForm.gis.attachment_url" label="Priedų URL" placeholder="https://..." />
                        <AppSelect
                            v-model.number="createForm.gis.wkid"
                            label="Koordinačių sistema (WKID)"
                            variant="white"
                            :placeholder="''"
                        >
                            <option :value="3346">LKS-94 (3346)</option>
                            <option :value="4326">WGS84 (4326)</option>
                            <option :value="3857">Web Mercator (3857)</option>
                        </AppSelect>
                        <label class="field checkbox full gis-mapper-toggle">
                            <input type="checkbox" v-model="createForm.gis.use_field_mapping" />
                            <span>Naudoti GIS laukų susiejimą</span>
                        </label>
                        <div v-if="createForm.gis.use_field_mapping" class="gis-mapper">
                            <div
                                v-for="row in GIS_FIELD_MAPPING_ROWS"
                                :key="row.key"
                                class="gis-mapper-row"
                            >
                                <label :for="'create-gis-map-' + row.key">{{ row.label }}</label>
                                <input
                                    :id="'create-gis-map-' + row.key"
                                    v-model="createForm.gis.field_mapping[row.key]"
                                    class="input"
                                    :placeholder="row.placeholder"
                                />
                            </div>
                        </div>
                    </template>
                </div>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="createForm.is_active" />
                    <span>Aktyvi įstaiga</span>
                </label>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="createForm.is_email_only" />
                    <span>Įstaiga be naudotojų (tik el. paštas)</span>
                </label>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Sukurti"
                    :primary-disabled="saving || !createForm.name?.trim() || !createForm.contact_email?.trim()"
                    @cancel="isCreateOpen = false"
                    @primary="createInstitution"
                />
            </template>
        </BaseModal>

        <BaseModal
            :show="isEditOpen"
            title="Redaguoti įstaigą"
            max-width="640px"
            @close="isEditOpen = false"
        >
            <div class="form-grid">
                <div class="field full">
                    <AppInput v-model="editForm.name" label="Pavadinimas" required />
                </div>
                <AppInput v-model="editForm.code" label="Kodas" />
                <AppSelect
                    v-model="editForm.type"
                    label="Tipas"
                    variant="white"
                    :placeholder="''"
                >
                    <option v-for="opt in INSTITUTION_TYPES" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </AppSelect>
                <AppInput v-model="editForm.contact_phone" label="Telefonas" />
                <AppInput v-model="editForm.contact_email" label="El. paštas" type="email" required />
                <div class="field full">
                    <AppInput v-model="editForm.contact_address" label="Adresas" />
                </div>
                <div class="field full">
                    <AppInput v-model="editForm.integration_dvs" label="Integracija (DVS)" />
                </div>
                <div class="field full">
                    <div class="gis-row">
                        <span class="field-label">GIS integracija</span>
                        <label class="gis-toggle">
                            <input type="checkbox" v-model="editForm.gis.enabled" />
                            <span>Įjungti</span>
                        </label>
                    </div>
                    <template v-if="editForm.gis.enabled">
                        <AppInput v-model="editForm.gis.generate_token_url" label="Token generavimo URL" placeholder="https://..." />
                        <AppInput v-model="editForm.gis.technical_username" label="Techninis vartotojas" />
                        <AppInput
                            v-model="editForm.gis.technical_password"
                            type="password"
                            label="Slaptažodis"
                            :placeholder="editForm.gis.has_technical_password ? 'Palikite tuščią, jei nekeičiate' : ''"
                            autocomplete="new-password"
                        />
                        <AppInput v-model="editForm.gis.client_type" label="Kliento tipas" placeholder="pvz. referer" />
                        <AppInput
                            v-model="editForm.gis.token_expiration_minutes"
                            type="number"
                            label="Token galiojimas (min.)"
                        />
                        <AppInput v-model="editForm.gis.feature_server_url" label="FeatureServer URL" placeholder="https://..." />
                        <AppInput v-model="editForm.gis.attachment_url" label="Priedų URL" placeholder="https://..." />
                        <AppSelect
                            v-model.number="editForm.gis.wkid"
                            label="Koordinačių sistema (WKID)"
                            variant="white"
                            :placeholder="''"
                        >
                            <option :value="3346">LKS-94 (3346)</option>
                            <option :value="4326">WGS84 (4326)</option>
                            <option :value="3857">Web Mercator (3857)</option>
                        </AppSelect>
                        <label class="field checkbox full gis-mapper-toggle">
                            <input type="checkbox" v-model="editForm.gis.use_field_mapping" />
                            <span>Naudoti GIS laukų susiejimą</span>
                        </label>
                        <div v-if="editForm.gis.use_field_mapping" class="gis-mapper">
                            <div
                                v-for="row in GIS_FIELD_MAPPING_ROWS"
                                :key="row.key"
                                class="gis-mapper-row"
                            >
                                <label :for="'edit-gis-map-' + row.key">{{ row.label }}</label>
                                <input
                                    :id="'edit-gis-map-' + row.key"
                                    v-model="editForm.gis.field_mapping[row.key]"
                                    class="input"
                                    :placeholder="row.placeholder"
                                />
                            </div>
                        </div>
                        <p v-if="editForm.gis.has_active_token" class="gis-hint">
                            Aktyvus tokenas: yra<span v-if="editForm.gis.active_token_expires_at">, galioja iki {{ editForm.gis.active_token_expires_at }}</span>
                        </p>
                    </template>
                </div>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="editForm.is_active" />
                    <span>Aktyvi įstaiga</span>
                </label>
                <label class="field checkbox full">
                    <input type="checkbox" v-model="editForm.is_email_only" />
                    <span>Įstaiga be naudotojų (tik el. paštas)</span>
                </label>
            </div>
            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Išsaugoti"
                    :primary-disabled="saving || !editForm.name?.trim() || !editForm.contact_email?.trim()"
                    @cancel="isEditOpen = false"
                    @primary="updateInstitution"
                />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showDeleteModal"
            title="Pašalinti įstaigą?"
            :message="deleteConfirmMessage"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            confirm-variant="danger"
            @close="closeDeleteModal"
            @confirm="deleteInstitution"
        />
    </div>
</template>

<style scoped>
.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 0;
}

.title {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.35;
    margin: 0;
    padding: 0;
}

.table-wrap {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    text-align: left;
    padding: 12px 10px;
    border-bottom: 1px solid var(--color-border-light);
    font-size: 13px;
    vertical-align: middle;
    box-sizing: border-box;
}

.table thead th {
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    background: var(--table-header-bg);
    color: var(--table-header-text);
}

.pill {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 12px;
}

.pill.active {
    background: #e8f5e9;
    color: #2e7d32;
}

.pill.inactive {
    background: #ffebee;
    color: #c62828;
}

.pill.email-only {
    background: #e3f2fd;
    color: #1565c0;
    margin-left: 6px;
}

.actions {
    white-space: nowrap;
}

.link-btn {
    border: none;
    background: transparent;
    color: var(--color-primary);
    cursor: pointer;
    padding: 0;
    font-size: 13px;
}

.link-btn:hover {
    text-decoration: none;
    opacity: 0.88;
}

.link-sep {
    margin: 0 6px;
    color: var(--color-gray-400);
}

.link-btn--danger {
    color: #c62828;
}

.link-btn--danger:hover {
    color: var(--color-primary-hover);
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: var(--color-error);
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.loading,
.empty {
    color: var(--color-text-muted);
    margin: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.form-grid :deep(.app-input-edit) {
    background-color: #fff;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.field.full {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field.checkbox {
    flex-direction: row;
    align-items: center;
}

.field span,
.field-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.input {
    padding: 8px 10px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 14px;
}

.input:focus {
    outline: none;
    border-color: var(--color-primary);
}

.gis-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 6px;
}

.gis-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-text);
}

.gis-hint {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--color-text-muted);
}

.gis-mapper-toggle {
    margin-top: 2px;
}

.gis-mapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 12px;
    padding: 10px;
    border: 1px solid var(--color-border-light);
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
    color: var(--color-text-muted);
}
</style>
