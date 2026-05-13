<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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
    reporter_value_mode: 'full_name',
    reporter_default_value: '',
    category_value_mapping: {},
    status_value_mapping: {},
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

const GIS_STATUS_OPTIONS = [
    { value: 'neuzregistruota', label: 'Neužregistruota' },
    { value: 'nauja', label: 'Nauja' },
    { value: 'nagrinejama', label: 'Nagrinėjama' },
    { value: 'grazinta_nagrinejimui', label: 'Grąžinta nagrinėjimui' },
    { value: 'isspresta', label: 'Išspręsta' },
    { value: 'neaktuali', label: 'Neaktuali' },
]

function defaultFieldMapping() {
    return { ...GIS_FIELD_MAPPING_DEFAULTS, category_value_mapping: {}, status_value_mapping: {} }
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
        service_base_url: '',
        service_layer_id: '',
        feature_server_url: '',
        attachment_url: '',
        metadata_url: '',
        metadata_layer_id: '',
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

function normalizeArcGisServiceBaseUrl(url) {
    const value = String(url ?? '').trim().replace(/[?#].*$/, '').replace(/\/+$/, '')
    return value.replace(/\/FeatureServer(?:\/.*)?$/i, '').replace(/\/+$/, '')
}

function serviceBaseUrlFromPayload(source) {
    return source?.service_base_url
        || normalizeArcGisServiceBaseUrl(source?.feature_server_url || source?.metadata_url || '')
}

function serviceLayerIdFromPayload(source) {
    if (source?.service_layer_id !== undefined && source?.service_layer_id !== null && source.service_layer_id !== '') {
        return String(source.service_layer_id)
    }
    if (source?.metadata_layer_id !== undefined && source?.metadata_layer_id !== null && source.metadata_layer_id !== '') {
        return String(source.metadata_layer_id)
    }
    const match = String(source?.feature_server_url ?? '').match(/\/FeatureServer\/(\d+)(?:\/|$|[?#])/i)
    return match?.[1] ?? ''
}

function derivedGisUrls(gis) {
    const rawBaseUrl = gis?.service_base_url || ''
    const baseUrl = normalizeArcGisServiceBaseUrl(rawBaseUrl)
    const pastedLayer = String(rawBaseUrl).match(/\/FeatureServer\/(\d+)(?:\/|$|[?#])/i)?.[1] ?? ''
    const layerId = String(gis?.service_layer_id ?? pastedLayer).trim()
    if (!baseUrl || !layerId) {
        return {
            feature_server_url: gis?.feature_server_url || null,
            attachment_url: gis?.attachment_url || null,
            metadata_url: gis?.metadata_url || null,
            metadata_layer_id: gis?.metadata_layer_id || null,
        }
    }

    const featureServerUrl = `${baseUrl}/FeatureServer/${layerId}`
    return {
        feature_server_url: featureServerUrl,
        attachment_url: featureServerUrl,
        metadata_url: `${baseUrl}/FeatureServer/queryDomains`,
        metadata_layer_id: layerId,
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
        service_base_url: serviceBaseUrlFromPayload(source),
        service_layer_id: serviceLayerIdFromPayload(source),
        feature_server_url: source.feature_server_url ?? '',
        attachment_url: source.attachment_url ?? '',
        metadata_url: source.metadata_url ?? '',
        metadata_layer_id: source.metadata_layer_id ?? '',
        wkid: source.wkid ?? 3346,
        use_field_mapping: Boolean(source.use_field_mapping),
        field_mapping: { ...defaultFieldMapping(), ...(source.field_mapping ?? {}) },
        has_active_token: Boolean(source.has_active_token),
        active_token_expires_at: source.active_token_expires_at ?? null,
    }
}

function gisSubmitPayload(gis) {
    const derivedUrls = derivedGisUrls(gis)
    const payload = {
        enabled: Boolean(gis?.enabled),
        generate_token_url: gis?.generate_token_url || null,
        technical_username: gis?.technical_username || null,
        client_type: gis?.client_type || null,
        token_expiration_minutes: gis?.token_expiration_minutes ? Number(gis.token_expiration_minutes) : null,
        service_base_url: normalizeArcGisServiceBaseUrl(gis?.service_base_url || '') || null,
        service_layer_id: gis?.service_layer_id !== '' && gis?.service_layer_id !== null && gis?.service_layer_id !== undefined
            ? Number(gis.service_layer_id)
            : null,
        ...derivedUrls,
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
const editGisMetadata = ref(null)
const editGisMetadataLoading = ref(false)
const editGisMetadataError = ref('')
const classifierTree = ref([])
const classifierTreeLoading = ref(false)
const classifierTreeError = ref('')
const editCategoryValueMappingOpen = ref(false)
const editStatusValueMappingOpen = ref(false)
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
    editGisMetadata.value = null
    editGisMetadataError.value = ''
    editCategoryValueMappingOpen.value = false
    editStatusValueMappingOpen.value = false
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
    if (editForm.value.gis.use_field_mapping) {
        loadEditGisMetadata()
        loadClassifierTree()
    }
}

const loadEditGisMetadata = async (force = false) => {
    if (!editForm.value?.id || editGisMetadataLoading.value) return
    if (!force && editGisMetadata.value) return

    editGisMetadataLoading.value = true
    editGisMetadataError.value = ''
    try {
        const { data } = await axios.get(adminApi.institutionGisMetadata(editForm.value.id))
        editGisMetadata.value = data
    } catch (e) {
        editGisMetadataError.value = e.response?.data?.message ?? 'Nepavyko gauti GIS laukų JSON.'
    } finally {
        editGisMetadataLoading.value = false
    }
}

const loadClassifierTree = async () => {
    if (classifierTreeLoading.value || classifierTree.value.length) return

    classifierTreeLoading.value = true
    classifierTreeError.value = ''
    try {
        const { data } = await axios.get(adminApi.classifiers)
        const types = data.types ?? []
        classifierTree.value = await Promise.all(
            types.map(async (type) => {
                const response = await axios.get(adminApi.classifierTypeNatures(type.id))
                return {
                    ...type,
                    children: response.data.natures ?? [],
                }
            }),
        )
    } catch (e) {
        classifierTreeError.value = e.response?.data?.message ?? 'Nepavyko gauti klasifikatoriaus reikšmių.'
    } finally {
        classifierTreeLoading.value = false
    }
}

const editCategoryGisFieldName = computed(() => {
    return (editForm.value.gis?.field_mapping?.category || GIS_FIELD_MAPPING_DEFAULTS.category).trim()
})

const editCategoryGisField = computed(() => {
    const fieldName = editCategoryGisFieldName.value
    return editGisMetadata.value?.fields?.find((field) => field.name === fieldName) ?? null
})

const editCategoryGisOptions = computed(() => editCategoryGisField.value?.coded_values ?? [])

const showEditCategoryValueMapping = computed(() => {
    return Boolean(editForm.value.gis?.use_field_mapping && editCategoryGisOptions.value.length)
})

const editStatusGisFieldName = computed(() => {
    return (editForm.value.gis?.field_mapping?.status || GIS_FIELD_MAPPING_DEFAULTS.status).trim()
})

const editStatusGisField = computed(() => {
    const fieldName = editStatusGisFieldName.value
    return editGisMetadata.value?.fields?.find((field) => field.name === fieldName) ?? null
})

const editStatusGisOptions = computed(() => editStatusGisField.value?.coded_values ?? [])

const showEditStatusValueMapping = computed(() => {
    return Boolean(editForm.value.gis?.use_field_mapping && editStatusGisOptions.value.length)
})

function ensureCategoryValueMapping() {
    if (!editForm.value.gis.field_mapping.category_value_mapping || Array.isArray(editForm.value.gis.field_mapping.category_value_mapping)) {
        editForm.value.gis.field_mapping.category_value_mapping = {}
    }
}

function categoryMappedCode(classifierValueId) {
    const mappedValue = editForm.value.gis?.field_mapping?.category_value_mapping?.[String(classifierValueId)]
    if (typeof mappedValue === 'string') return mappedValue
    return mappedValue?.code ?? ''
}

function setCategoryValueMapping(classifierValueId, code) {
    ensureCategoryValueMapping()
    const key = String(classifierValueId)
    if (!code) {
        delete editForm.value.gis.field_mapping.category_value_mapping[key]
        return
    }

    const selected = editCategoryGisOptions.value.find((option) => option.code === code)
    editForm.value.gis.field_mapping.category_value_mapping[key] = {
        code,
        name: selected?.name ?? code,
    }
}

function ensureStatusValueMapping() {
    if (!editForm.value.gis.field_mapping.status_value_mapping || Array.isArray(editForm.value.gis.field_mapping.status_value_mapping)) {
        editForm.value.gis.field_mapping.status_value_mapping = {}
    }
}

function statusMappedCode(statusValue) {
    const mappedValue = editForm.value.gis?.field_mapping?.status_value_mapping?.[String(statusValue)]
    if (typeof mappedValue === 'string') return mappedValue
    return mappedValue?.code ?? ''
}

function setStatusValueMapping(statusValue, code) {
    ensureStatusValueMapping()
    const key = String(statusValue)
    if (!code) {
        delete editForm.value.gis.field_mapping.status_value_mapping[key]
        return
    }

    const selected = editStatusGisOptions.value.find((option) => option.code === code)
    editForm.value.gis.field_mapping.status_value_mapping[key] = {
        code,
        name: selected?.name ?? code,
    }
}

watch(
    () => editForm.value.gis?.use_field_mapping,
    (enabled) => {
        if (isEditOpen.value && enabled) {
            loadEditGisMetadata()
            loadClassifierTree()
        }
    },
)

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
                        <AppInput
                            v-model="createForm.gis.service_base_url"
                            label="ArcGIS paslaugos URL"
                            placeholder="https://digital.kaunas.lt/arcgis/rest/services/Testavimai/Dispeceris_update_Kaunas_tvarko"
                        />
                        <AppInput
                            v-model="createForm.gis.service_layer_id"
                            type="number"
                            label="Sluoksnio ID"
                            placeholder="1"
                        />
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
                                <div v-if="row.key === 'reporter'" class="gis-reporter-mode">
                                    <select class="input" v-model="createForm.gis.field_mapping.reporter_value_mode">
                                        <option value="full_name">Siųsti vardą ir pavardę</option>
                                        <option value="resident">Siųsti tik Gyventojas</option>
                                        <option value="custom">Siųsti administratoriaus tekstą</option>
                                    </select>
                                    <input
                                        v-if="createForm.gis.field_mapping.reporter_value_mode === 'custom'"
                                        v-model="createForm.gis.field_mapping.reporter_default_value"
                                        class="input"
                                        placeholder="Numatytoji Pranešėjo reikšmė"
                                    />
                                </div>
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
                        <AppInput
                            v-model="editForm.gis.service_base_url"
                            label="ArcGIS paslaugos URL"
                            placeholder="https://digital.kaunas.lt/arcgis/rest/services/Testavimai/Dispeceris_update_Kaunas_tvarko"
                        />
                        <AppInput
                            v-model="editForm.gis.service_layer_id"
                            type="number"
                            label="Sluoksnio ID"
                            placeholder="1"
                        />
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
                                <div v-if="row.key === 'reporter'" class="gis-reporter-mode">
                                    <select class="input" v-model="editForm.gis.field_mapping.reporter_value_mode">
                                        <option value="full_name">Siųsti vardą ir pavardę</option>
                                        <option value="resident">Siųsti tik Gyventojas</option>
                                        <option value="custom">Siųsti administratoriaus tekstą</option>
                                    </select>
                                    <input
                                        v-if="editForm.gis.field_mapping.reporter_value_mode === 'custom'"
                                        v-model="editForm.gis.field_mapping.reporter_default_value"
                                        class="input"
                                        placeholder="Numatytoji Pranešėjo reikšmė"
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-if="editForm.gis.use_field_mapping" class="gis-metadata">
                            <div class="gis-metadata-header">
                                <span>GIS laukai iš FeatureServer</span>
                                <button
                                    type="button"
                                    class="link-btn"
                                    :disabled="editGisMetadataLoading"
                                    @click="loadEditGisMetadata(true)"
                                >
                                    {{ editGisMetadataLoading ? 'Gaunama...' : 'Gauti GIS laukus' }}
                                </button>
                            </div>
                            <p v-if="editGisMetadataError" class="gis-metadata-error">{{ editGisMetadataError }}</p>
                            <p v-else-if="editGisMetadataLoading" class="gis-hint">Gaunamas GIS laukų JSON...</p>
                            <div v-else-if="editGisMetadata?.fields?.length" class="gis-metadata-list">
                                <div
                                    v-for="field in editGisMetadata.fields"
                                    :key="field.name"
                                    class="gis-metadata-field"
                                >
                                    <div class="gis-metadata-field-top">
                                        <strong>{{ field.name }}</strong>
                                        <span v-if="field.alias && field.alias !== field.name">{{ field.alias }}</span>
                                        <span v-if="field.type">{{ field.type }}</span>
                                    </div>
                                    <div v-if="field.coded_values?.length" class="gis-coded-values">
                                        <span
                                            v-for="option in field.coded_values"
                                            :key="field.name + '-' + option.code"
                                            class="gis-coded-value"
                                        >
                                            {{ option.name }}: {{ option.code }}
                                        </span>
                                    </div>
                                    <p v-else class="gis-metadata-empty">Pasirinkimų nėra</p>
                                </div>
                            </div>
                            <p v-else-if="editGisMetadata" class="gis-hint">
                                GIS JSON laukų nerasta.
                            </p>
                            <p v-else class="gis-hint">
                                Paspauskite „Gauti GIS laukus“, kad pamatytumėte laukus ir jų pasirinkimus.
                            </p>
                            <div
                                v-if="showEditCategoryValueMapping"
                                class="gis-value-mapper"
                                :class="{ 'gis-value-mapper--collapsed': !editCategoryValueMappingOpen }"
                            >
                                <div class="gis-value-mapper-header">
                                    <span>Kategorija / tipas reikšmių susiejimas</span>
                                    <span>{{ editCategoryGisFieldName }}</span>
                                    <button
                                        type="button"
                                        class="link-btn"
                                        @click="editCategoryValueMappingOpen = !editCategoryValueMappingOpen"
                                    >
                                        {{ editCategoryValueMappingOpen ? 'Slėpti' : 'Rodyti' }}
                                    </button>
                                </div>
                                <p v-if="!editCategoryValueMappingOpen" class="gis-hint">
                                    Susiejimai paslėpti, kad forma užimtų mažiau vietos.
                                </p>
                                <p v-if="classifierTreeLoading" class="gis-hint">Kraunamos klasifikatoriaus reikšmės...</p>
                                <p v-else-if="classifierTreeError" class="gis-metadata-error">{{ classifierTreeError }}</p>
                                <div v-else-if="classifierTree.length" class="gis-classifier-tree">
                                    <div
                                        v-for="type in classifierTree"
                                        :key="'classifier-type-' + type.id"
                                        class="gis-classifier-group"
                                    >
                                        <div class="gis-classifier-group-title">{{ type.name }}</div>
                                        <div class="gis-value-map-row gis-value-map-row--type">
                                            <span>Tipas: {{ type.name }}</span>
                                            <select
                                                class="input"
                                                :value="categoryMappedCode(type.id)"
                                                @change="setCategoryValueMapping(type.id, $event.target.value)"
                                            >
                                                <option value="">Siųsti esamą reikšmę</option>
                                                <option
                                                    v-for="option in editCategoryGisOptions"
                                                    :key="'type-' + type.id + '-' + option.code"
                                                    :value="option.code"
                                                >
                                                    {{ option.name }}: {{ option.code }}
                                                </option>
                                            </select>
                                        </div>
                                        <div
                                            v-for="nature in type.children"
                                            :key="'classifier-nature-' + nature.id"
                                            class="gis-value-map-row gis-value-map-row--nature"
                                        >
                                            <span>Pobūdis: {{ nature.name }}</span>
                                            <select
                                                class="input"
                                                :value="categoryMappedCode(nature.id)"
                                                @change="setCategoryValueMapping(nature.id, $event.target.value)"
                                            >
                                                <option value="">Siųsti esamą reikšmę</option>
                                                <option
                                                    v-for="option in editCategoryGisOptions"
                                                    :key="'nature-' + nature.id + '-' + option.code"
                                                    :value="option.code"
                                                >
                                                    {{ option.name }}: {{ option.code }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="gis-hint">Klasifikatoriaus reikšmių nėra.</p>
                            </div>
                            <div
                                v-if="showEditStatusValueMapping"
                                class="gis-value-mapper"
                                :class="{ 'gis-value-mapper--collapsed': !editStatusValueMappingOpen }"
                            >
                                <div class="gis-value-mapper-header">
                                    <span>Būsena reikšmių susiejimas</span>
                                    <span>{{ editStatusGisFieldName }}</span>
                                    <button
                                        type="button"
                                        class="link-btn"
                                        @click="editStatusValueMappingOpen = !editStatusValueMappingOpen"
                                    >
                                        {{ editStatusValueMappingOpen ? 'Slėpti' : 'Rodyti' }}
                                    </button>
                                </div>
                                <p v-if="!editStatusValueMappingOpen" class="gis-hint">
                                    Susiejimai paslėpti, kad forma užimtų mažiau vietos.
                                </p>
                                <div v-else class="gis-classifier-tree">
                                    <div
                                        v-for="status in GIS_STATUS_OPTIONS"
                                        :key="'status-map-' + status.value"
                                        class="gis-value-map-row"
                                    >
                                        <span>{{ status.label }}</span>
                                        <select
                                            class="input"
                                            :value="statusMappedCode(status.value)"
                                            @change="setStatusValueMapping(status.value, $event.target.value)"
                                        >
                                            <option value="">Siųsti esamą reikšmę</option>
                                            <option
                                                v-for="option in editStatusGisOptions"
                                                :key="'status-' + status.value + '-' + option.code"
                                                :value="option.code"
                                            >
                                                {{ option.name }}: {{ option.code }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-if="editGisMetadata?.fields?.length && editCategoryGisField && !editCategoryGisOptions.length"
                                class="gis-hint"
                            >
                                Pasirinktas Kategorija / tipas GIS laukas neturi pasirinkimų.
                            </p>
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
    text-align: center;
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

.gis-reporter-mode {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.gis-metadata {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border: 1px solid var(--color-border-light);
    border-radius: 8px;
    background: #ffffff;
}

.gis-metadata-header,
.gis-metadata-field-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.gis-metadata-header span {
    font-size: 13px;
    font-weight: 700;
}

.gis-metadata-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 260px;
    overflow-y: auto;
}

.gis-metadata-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px;
    border: 1px solid var(--color-border-light);
    border-radius: 8px;
    background: #f9fafb;
}

.gis-metadata-field-top {
    justify-content: flex-start;
    flex-wrap: wrap;
}

.gis-metadata-field-top strong {
    font-size: 13px;
}

.gis-metadata-field-top span,
.gis-metadata-empty {
    margin: 0;
    font-size: 12px;
    color: var(--color-text-muted);
}

.gis-coded-values {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.gis-coded-value {
    display: inline-flex;
    max-width: 100%;
    padding: 4px 6px;
    border-radius: 6px;
    background: #e8f0fe;
    color: #174ea6;
    font-size: 12px;
    line-height: 1.3;
}

.gis-metadata-error {
    margin: 0;
    font-size: 12px;
    color: var(--color-error);
}

.gis-value-mapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border: 1px solid var(--color-border-light);
    border-radius: 8px;
    background: #f9fafb;
}

.gis-value-mapper--collapsed .gis-classifier-tree,
.gis-value-mapper--collapsed .gis-metadata-error,
.gis-value-mapper--collapsed > .gis-hint:not(:first-of-type) {
    display: none;
}

.gis-value-mapper-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
}

.gis-value-mapper-header span:last-child {
    font-size: 12px;
    color: var(--color-text-muted);
}

.gis-classifier-tree {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 320px;
    overflow-y: auto;
}

.gis-classifier-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.gis-classifier-group-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--color-text-muted);
}

.gis-value-map-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(180px, 1.2fr);
    gap: 8px;
    align-items: center;
}

.gis-value-map-row span {
    min-width: 0;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.gis-value-map-row--nature span {
    padding-left: 12px;
}
</style>
