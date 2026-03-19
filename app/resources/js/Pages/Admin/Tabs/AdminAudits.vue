<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import AppInput from '@/Components/App/AppInput.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'

const EVENT_TYPE_LABELS = {
    login: 'Prisijungimas',
    logout: 'Atsijungimas',
    user_action: 'Naudotojo veiksmas',
    integration: 'Integracija',
    system: 'Sistema',
    audit_config: 'Audito nustatymai',
}

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const showSuccessModal = ref(false)
const data = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(20)

const filters = ref({
    date_from: '',
    date_to: '',
    event_type: '',
    result: '',
    search: '',
})

const settings = ref({
    enabled: true,
    retention_days: 365,
    retention_is_active: true,
})
const settingsForm = ref({ ...settings.value })
const savingSettings = ref(false)
const exporting = ref(false)

async function loadSettings() {
    try {
        const { data: res } = await axios.get('/admin/audit-settings')
        settings.value = {
            enabled: res.enabled ?? true,
            retention_days: res.retention_days ?? 365,
            retention_is_active: res.retention_is_active ?? true,
        }
        settingsForm.value = { ...settings.value }
    } catch (_) {}
}

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const params = new URLSearchParams()
        params.set('page', String(currentPage.value))
        params.set('per_page', String(perPage.value))
        if (filters.value.date_from) params.set('date_from', filters.value.date_from)
        if (filters.value.date_to) params.set('date_to', filters.value.date_to)
        if (filters.value.event_type) params.set('event_type', filters.value.event_type)
        if (filters.value.result) params.set('result', filters.value.result)
        if (filters.value.search?.trim()) params.set('search', filters.value.search.trim())
        const { data: res } = await axios.get('/admin/audit-logs?' + params.toString())
        data.value = res.data ?? []
        currentPage.value = res.current_page ?? 1
        lastPage.value = res.last_page ?? 1
        total.value = res.total ?? 0
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko užkrauti žurnalo.'
    } finally {
        loading.value = false
    }
}

async function saveSettings() {
    savingSettings.value = true
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.put('/admin/audit-settings', {
            enabled: settingsForm.value.enabled,
            retention_days: settingsForm.value.retention_days ? Number(settingsForm.value.retention_days) : null,
            retention_is_active: settingsForm.value.retention_is_active,
        })
        await loadSettings()
        successMessage.value = 'Nustatymai išsaugoti.'
        showSuccessModal.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko išsaugoti.'
    } finally {
        savingSettings.value = false
    }
}

function exportCsv() {
    const params = new URLSearchParams()
    if (filters.value.date_from) params.set('date_from', filters.value.date_from)
    if (filters.value.date_to) params.set('date_to', filters.value.date_to)
    if (filters.value.event_type) params.set('event_type', filters.value.event_type)
    if (filters.value.result) params.set('result', filters.value.result)
    if (filters.value.search?.trim()) params.set('search', filters.value.search.trim())
    window.location.href = '/admin/audit-logs/export?' + params.toString()
}

function formatDate(str) {
    if (!str) return '—'
    return new Date(str).toLocaleString('lt-LT')
}

function eventTypeLabel(type) {
    return EVENT_TYPE_LABELS[type] ?? type ?? '—'
}

function subjectDisplay(row) {
    if (!row.subject_type) return '—'
    return row.subject_id ? `${row.subject_type}#${row.subject_id}` : row.subject_type
}

watch([() => filters.value.date_from, () => filters.value.date_to, () => filters.value.event_type, () => filters.value.result, () => filters.value.search], () => {
    currentPage.value = 1
    load()
})

load()
loadSettings()
</script>

<template>
    <div class="admin-tab audits-tab">
        <h2 class="title">Auditai</h2>
        <p class="desc">Žurnalas apima prisijungimus, naudotojų veiksmus ir sistemos įvykius. Prieiga tik sistemos administratoriui.</p>

        <div class="settings-block">
            <h3 class="block-title">Nustatymai</h3>
            <div class="settings-form">
                <label class="setting-row">
                    <AppCheckbox v-model="settingsForm.enabled" />
                    <span>Žurnalavimas įjungtas</span>
                </label>
                <label class="setting-row setting-row--with-input">
                    <span class="label">Saugojimo laikotarpis (dienos)</span>
                    <span class="setting-input-wrap">
                        <AppInput v-model.number="settingsForm.retention_days" type="number" min="1" max="3650" class="input-narrow" />
                    </span>
                </label>
                <label class="setting-row">
                    <AppCheckbox v-model="settingsForm.retention_is_active" />
                    <span>Taikyti saugojimo laikotarpį (seni įrašai trynami)</span>
                </label>
                <AppButton text="Išsaugoti nustatymus" variant="primary" as-button :disabled="savingSettings" @click="saveSettings" />
            </div>
        </div>

        <div class="filters-block">
            <h3 class="block-title">Filtrai ir paieška</h3>
            <div class="filters-row">
                <AppDatePicker
                    v-model="filters.date_from"
                    label="Data nuo"
                    placeholder="Pasirinkite datą"
                    class="filter-date"
                />
                <AppDatePicker
                    v-model="filters.date_to"
                    label="Data iki"
                    placeholder="Pasirinkite datą"
                    :min-date="filters.date_from || undefined"
                    class="filter-date"
                />
                <div class="filter-group">
                    <label class="filter-label">Įvykio tipas</label>
                    <select v-model="filters.event_type" class="filter-select">
                        <option value="">Visi</option>
                        <option v-for="(label, key) in EVENT_TYPE_LABELS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Rezultatas</label>
                    <select v-model="filters.result" class="filter-select">
                        <option value="">Visi</option>
                        <option value="success">Sėkmė</option>
                        <option value="failure">Klaida</option>
                    </select>
                </div>
                <AppInput v-model="filters.search" placeholder="Ieškoti (veiksmas, žinutė, atlikėjas)" class="filter-search" />
                <AppButton text="Eksportuoti CSV" variant="secondary" as-button :disabled="exporting" @click="exportCsv" />
            </div>
        </div>

        <SuccessModal :show="showSuccessModal" :message="successMessage" @close="showSuccessModal = false; successMessage = ''" />
        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <div v-if="loading" class="loading">Kraunama...</div>

        <div v-else class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data ir laikas</th>
                        <th>Atlikėjas</th>
                        <th>Įvykio tipas</th>
                        <th>Veiksmas</th>
                        <th>Subjektas</th>
                        <th>Rezultatas</th>
                        <th>Žinutė</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data" :key="row.id">
                        <td>{{ formatDate(row.occurred_at) }}</td>
                        <td>{{ row.actor_identifier ?? '—' }}</td>
                        <td>{{ eventTypeLabel(row.event_type) }}</td>
                        <td>{{ row.action ?? '—' }}</td>
                        <td>{{ subjectDisplay(row) }}</td>
                        <td>{{ row.result === 'success' ? 'Sėkmė' : 'Klaida' }}</td>
                        <td>{{ row.message ?? '—' }}</td>
                    </tr>
                    <tr v-if="!data.length">
                        <td colspan="7" class="empty-cell">Nėra įrašų pagal pasirinktus filtrus.</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="lastPage > 1" class="pagination">
                <button
                    type="button"
                    class="page-btn"
                    :disabled="currentPage <= 1"
                    @click="currentPage--; load()"
                >
                    Ankstesnis
                </button>
                <span class="page-info">{{ currentPage }} / {{ lastPage }} (iš viso {{ total }})</span>
                <button
                    type="button"
                    class="page-btn"
                    :disabled="currentPage >= lastPage"
                    @click="currentPage++; load()"
                >
                    Kitas
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.audits-tab {
    padding: 8px 0;
}
.title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 4px 0;
}
.desc {
    font-size: 14px;
    color: var(--color-text-muted);
    margin: 0 0 24px 0;
}
.settings-block,
.filters-block {
    margin-bottom: 24px;
}
.block-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 12px 0;
}
.settings-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 480px;
}
.setting-row {
    display: flex;
    align-items: center;
    gap: 20px;
    cursor: pointer;
    font-size: 14px;
}
.setting-row .label {
    min-width: 220px;
    flex-shrink: 0;
}
.setting-row--with-input .setting-input-wrap {
    margin-left: 24px;
    flex-shrink: 0;
}
.input-narrow {
    width: 100px;
}
.filters-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
}
.filter-date {
    min-width: 160px;
}
.filter-search {
    min-width: 220px;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.filter-label {
    font-size: 12px;
    color: var(--color-text-muted);
}
.filter-select {
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
    min-width: 160px;
}
.error {
    color: var(--color-error);
    font-size: 14px;
    margin: 0 0 12px 0;
}
.loading {
    color: var(--color-text-muted);
    font-size: 14px;
}
.table-wrap {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.data-table th,
.data-table td {
    padding: 12px 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
    box-sizing: border-box;
}
.data-table th {
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    background: var(--table-header-bg);
    color: var(--table-header-text);
}
.empty-cell {
    color: var(--color-text-muted);
    padding: 24px 12px;
    text-align: center;
    font-size: 14px;
}
.pagination {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}
.page-btn {
    padding: 6px 12px;
    font-size: 14px;
    border: 1px solid var(--color-border);
    background: var(--color-white);
    cursor: pointer;
    border-radius: 6px;
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-info {
    font-size: 14px;
    color: var(--color-text-muted);
}
</style>
