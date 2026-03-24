<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'

const loading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const showSuccessModal = ref(false)

const users = ref([])
const institutions = ref([])
const problems = ref([])
const classifierValues = ref([])
const retentionRules = ref({
    user: null,
    institution: null,
    problem: null,
    classifier_value: null,
})

const TAB_ENTITY_MAP = {
    users: 'user',
    institutions: 'institution',
    problems: 'problem',
    classifiers: 'classifier_value',
}

const settingsForm = ref({
    retention_days: '',
    is_active: true,
})
const savingSettings = ref(false)

const activeTab = ref('users')

const tabs = [
    { key: 'users', label: 'Naudotojai' },
    { key: 'institutions', label: 'Įstaigos' },
    { key: 'problems', label: 'Problemos' },
    { key: 'classifiers', label: 'Klasifikatoriai' },
]

const EMPTY_MESSAGES = {
    users: 'Nėra archyvuotų naudotojų.',
    institutions: 'Nėra archyvuotų įstaigų.',
    problems: 'Nėra archyvuotų problemų.',
    classifiers: 'Nėra archyvuotų klasifikatorių.',
}
const emptyTabMessage = computed(() => EMPTY_MESSAGES[activeTab.value] ?? '')

function syncSettingsFormFromRule() {
    const entityType = TAB_ENTITY_MAP[activeTab.value]
    const r = retentionRules.value[entityType]
    settingsForm.value = {
        retention_days: r?.delete_after_days ?? r?.archive_after_days ?? '',
        is_active: r?.is_active ?? true,
    }
}

const load = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get('/admin/archive')
        retentionRules.value = data.retentionRules || {
            user: null,
            institution: null,
            problem: null,
            classifier_value: null,
        }
        users.value = data.users || []
        institutions.value = data.institutions || []
        problems.value = data.problems || []
        classifierValues.value = data.classifierValues || []
        syncSettingsFormFromRule()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko užkrauti duomenų.'
    } finally {
        loading.value = false
    }
}

const saveSettings = async () => {
    savingSettings.value = true
    errorMessage.value = ''
    successMessage.value = ''
    try {
        const entityType = TAB_ENTITY_MAP[activeTab.value]
        const days = settingsForm.value.retention_days ? Number(settingsForm.value.retention_days) : null
        await axios.put('/admin/archive/retention-rule', {
            entity_type: entityType,
            retention_days: days,
            is_active: settingsForm.value.is_active,
        })
        successMessage.value = 'Nustatymai išsaugoti.'
        showSuccessModal.value = true
        await load()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko išsaugoti nustatymų.'
    } finally {
        savingSettings.value = false
    }
}

const formatDate = (str) => {
    if (!str) return '—'
    return new Date(str).toLocaleDateString('lt-LT')
}

const restoring = ref(null)

const RESTORE_CONFIG = {
    users: { path: (id) => `/admin/archive/users/${id}/restore`, key: 'user', successMsg: 'Naudotojas atkurtas.' },
    institutions: { path: (id) => `/admin/archive/institutions/${id}/restore`, key: 'institution', successMsg: 'Įstaiga atkurta.' },
    problems: { path: (id) => `/admin/archive/problems/${id}/restore`, key: 'problem', successMsg: 'Problema atkurta.' },
    classifiers: { path: (id) => `/admin/archive/classifier-values/${id}/restore`, key: 'classifier', successMsg: 'Klasifikatorius atkurtas.' },
}

async function restore(type, item) {
    const cfg = RESTORE_CONFIG[type]
    if (!cfg || !item?.id) return
    restoring.value = `${cfg.key}-${item.id}`
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.post(cfg.path(item.id))
        successMessage.value = cfg.successMsg
        showSuccessModal.value = true
        await load()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko atkurti.'
    } finally {
        restoring.value = null
    }
}

function restoringKey(type, id) {
    const cfg = RESTORE_CONFIG[type]
    return cfg ? `${cfg.key}-${id}` : ''
}

watch(activeTab, syncSettingsFormFromRule)
onMounted(load)
</script>

<template>
    <div class="admin-archive">
        <h2 class="title">Archyvavimas</h2>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />

        <div class="settings-block">
            <h3 class="settings-title">Saugojimo terminas ({{ tabs.find(t => t.key === activeTab)?.label ?? activeTab }})</h3>
            <div class="settings-row">
                <label class="field-label">Saugoti dienų</label>
                <AppInput v-model.number="settingsForm.retention_days" type="number" placeholder="365" class="input-narrow" />
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="savingSettings"
                    @click="saveSettings"
                />
            </div>
            <label class="checkbox-below">
                <AppCheckbox v-model="settingsForm.is_active" />
                <span>Taisyklė įjungta</span>
            </label>
        </div>

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else>
            <h3 class="section-title">Archyvuoti įrašai</h3>
            <div class="tabs">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    :class="['tab', { active: activeTab === t.key }]"
                    @click="activeTab = t.key"
                >
                    {{ t.label }}
                </button>
            </div>

            <div class="table-wrap">
                <table v-if="activeTab === 'users' && users.length" class="table">
                    <thead>
                        <tr>
                            <th>Vardas</th>
                            <th>El. paštas</th>
                            <th>Archyvuota</th>
                            <th>Ištrinama</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id">
                            <td>{{ u.name }}</td>
                            <td>{{ u.email }}</td>
                            <td>{{ formatDate(u.archived_at) }}</td>
                            <td>{{ formatDate(u.delete_at) }}</td>
                            <td>
                                <AppButton
                                    text="Atkurti"
                                    variant="primary"
                                    as-button
                                    :disabled="restoring === restoringKey('users', u.id)"
                                    @click="restore('users', u)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table v-else-if="activeTab === 'institutions' && institutions.length" class="table">
                    <thead>
                        <tr>
                            <th>Pavadinimas</th>
                            <th>Archyvuota</th>
                            <th>Ištrinama</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in institutions" :key="i.id">
                            <td>{{ i.name }}</td>
                            <td>{{ formatDate(i.archived_at) }}</td>
                            <td>{{ formatDate(i.delete_at) }}</td>
                            <td>
                                <AppButton
                                    text="Atkurti"
                                    variant="primary"
                                    as-button
                                    :disabled="restoring === restoringKey('institutions', i.id)"
                                    @click="restore('institutions', i)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table v-else-if="activeTab === 'problems' && problems.length" class="table">
                    <thead>
                        <tr>
                            <th>Nr.</th>
                            <th>Aprašymas</th>
                            <th>Archyvuota</th>
                            <th>Ištrinama</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in problems" :key="p.id">
                            <td>{{ p.internal_number }}</td>
                            <td>{{ p.description_preview || '—' }}</td>
                            <td>{{ formatDate(p.archived_at) }}</td>
                            <td>{{ formatDate(p.delete_at) }}</td>
                            <td>
                                <AppButton
                                    text="Atkurti"
                                    variant="primary"
                                    as-button
                                    :disabled="restoring === restoringKey('problems', p.id)"
                                    @click="restore('problems', p)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table v-else-if="activeTab === 'classifiers' && classifierValues.length" class="table">
                    <thead>
                        <tr>
                            <th>Pavadinimas</th>
                            <th>Tipas</th>
                            <th>Archyvuota</th>
                            <th>Ištrinama</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in classifierValues" :key="c.id">
                            <td>{{ c.name }}</td>
                            <td>{{ c.kind }}</td>
                            <td>{{ formatDate(c.archived_at) }}</td>
                            <td>{{ formatDate(c.delete_at) }}</td>
                            <td>
                                <AppButton
                                    text="Atkurti"
                                    variant="primary"
                                    as-button
                                    :disabled="restoring === restoringKey('classifiers', c.id)"
                                    @click="restore('classifiers', c)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="empty">
                    {{ emptyTabMessage }}
                </p>
            </div>
        </template>
    </div>
</template>

<style scoped>
.admin-archive {
    padding: 24px 32px;
    max-width: 1000px;
}
.title {
    font-size: var(--admin-title-size, 16px);
    font-weight: 700;
    margin: 0 0 8px 0;
}
.settings-block {
    background: #f8f9fb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.settings-title {
    font-size: var(--admin-font-size, 14px);
    font-weight: 600;
    margin: 0 0 12px 0;
    color: #333;
}
.settings-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.settings-row .field-label {
    font-size: 14px;
    color: #333;
}
.settings-row .input-narrow {
    width: 100px;
}
.checkbox-below {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}
.section-title {
    font-size: var(--admin-title-size, 16px);
    font-weight: 600;
    margin: 0 0 12px 0;
    color: #333;
}
.tabs {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}
.tab {
    padding: 10px 20px;
    border: none;
    background: none;
    font-size: 14px;
    color: #555;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
}
.tab:hover {
    color: #333;
}
.tab.active {
    color: var(--color-primary, #1946A0);
    font-weight: 600;
    border-bottom-color: var(--color-primary, #1946A0);
}
.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.table th,
.table td {
    padding: 12px 14px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
    box-sizing: border-box;
}
.table th {
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    background: var(--table-header-bg);
    color: var(--table-header-text);
}
.empty {
    color: #666;
    font-size: 14px;
    margin: 0;
    padding: 24px 0;
}
.error {
    color: #b91c1c;
    font-size: 14px;
    margin: 0 0 16px 0;
}
.loading {
    color: #666;
    font-size: 14px;
}
</style>
