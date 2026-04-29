<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import { debounce } from 'lodash-es'
import { adminApi } from '@/constants/api/adminApi'

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
const isSyncingSettingsFromServer = ref(false)

const activeTab = ref('users')

const tabs = [
    { key: 'users', label: 'Naudotojai' },
    { key: 'institutions', label: 'Įstaigos' },
    { key: 'problems', label: 'Problemos' },
    { key: 'classifiers', label: 'Rūšys ir pobūdžiai' },
]

const EMPTY_MESSAGES = {
    users: 'Nėra archyvuotų naudotojų.',
    institutions: 'Nėra archyvuotų įstaigų.',
    problems: 'Nėra archyvuotų problemų.',
    classifiers: 'Nėra archyvuotų rūšių ar pobūdžių.',
}
const emptyTabMessage = computed(() => EMPTY_MESSAGES[activeTab.value] ?? '')

function syncSettingsFormFromRule() {
    const entityType = TAB_ENTITY_MAP[activeTab.value]
    const r = retentionRules.value[entityType]
    isSyncingSettingsFromServer.value = true
    settingsForm.value = {
        retention_days: r?.delete_after_days ?? '',
        is_active: r?.is_active ?? true,
    }
    nextTick(() => {
        isSyncingSettingsFromServer.value = false
    })
}

const load = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.archive)
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
    if (savingSettings.value) return
    savingSettings.value = true
    errorMessage.value = ''
    successMessage.value = ''
    try {
        const entityType = TAB_ENTITY_MAP[activeTab.value]
        const days = settingsForm.value.retention_days ? Number(settingsForm.value.retention_days) : null
        await axios.put(adminApi.archiveRetentionRule, {
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
    if (!str) return '-'
    return new Date(str).toLocaleDateString('lt-LT')
}

function currentRuleForActiveTab() {
    const entityType = TAB_ENTITY_MAP[activeTab.value]
    return retentionRules.value?.[entityType] ?? null
}

function hasPendingSettingsChanges() {
    const currentRule = currentRuleForActiveTab()
    const currentDays = currentRule?.delete_after_days ?? null
    const currentActive = currentRule?.is_active ?? true
    const formDays = settingsForm.value.retention_days === '' ? null : Number(settingsForm.value.retention_days)
    return formDays !== currentDays || settingsForm.value.is_active !== currentActive
}

const debouncedSaveSettings = debounce(() => {
    if (isSyncingSettingsFromServer.value || !settingsForm.value.is_active) {
        return
    }
    if (!hasPendingSettingsChanges()) {
        return
    }
    void saveSettings()
}, 450)

function handleRuleToggle(nextValue) {
    settingsForm.value.is_active = nextValue
    debouncedSaveSettings.cancel()
    if (!isSyncingSettingsFromServer.value && hasPendingSettingsChanges()) {
        void saveSettings()
    }
}

const restoring = ref(null)
const destroying = ref(null)
const confirmDeleteUser = ref(null)

const RESTORE_CONFIG = {
    users: { path: adminApi.archiveRestoreUser, key: 'user', successMsg: 'Naudotojas atkurtas.' },
    institutions: { path: adminApi.archiveRestoreInstitution, key: 'institution', successMsg: 'Įstaiga atkurta.' },
    problems: { path: adminApi.archiveRestoreProblem, key: 'problem', successMsg: 'Problema atkurta.' },
    classifiers: { path: adminApi.archiveRestoreClassifierValue, key: 'classifier', successMsg: 'Rūšis arba pobūdis atkurtas.' },
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

async function destroyUser() {
    const user = confirmDeleteUser.value
    if (!user?.id) return
    destroying.value = user.id
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.delete(adminApi.archiveDestroyUser(user.id))
        confirmDeleteUser.value = null
        successMessage.value = 'Naudotojas galutinai pašalintas.'
        showSuccessModal.value = true
        await load()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pašalinti naudotojo.'
    } finally {
        destroying.value = null
    }
}

watch(activeTab, syncSettingsFormFromRule)
watch(
    () => settingsForm.value.retention_days,
    () => {
        if (isSyncingSettingsFromServer.value || !settingsForm.value.is_active) {
            return
        }
        debouncedSaveSettings()
    },
)

onBeforeUnmount(() => {
    debouncedSaveSettings.cancel()
})
onMounted(load)
</script>

<template>
    <div class="admin-archive">
        <div class="admin-tab-intro">
            <h2 class="admin-tab-intro__title">Archyvavimas</h2>
            <p class="admin-tab-intro__desc">
                Nustatykite, po kiek dienų archyvuoti įrašai bus galutinai ištrinti, ir peržiūrėkite archyvuotus duomenis.
            </p>
        </div>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="showSuccessModal = false; successMessage = ''"
        />

        <div class="settings-card-wrap">
            <div class="settings-card">
                <h3 class="settings-card__title">Saugojimo terminas</h3>
                <p class="settings-card__hint">
                    Taikoma skyriui {{ tabs.find(t => t.key === activeTab)?.label ?? activeTab }}.
                </p>
                <div class="settings-card__row">
                    <label class="settings-card__label" for="archive-retention-days">Saugoti dienų</label>
                    <AppInput
                        id="archive-retention-days"
                        v-model.number="settingsForm.retention_days"
                        type="number"
                        placeholder="365"
                        class="input-narrow"
                    />
                </div>
                <div class="settings-card__switch-row">
                    <span class="settings-card__switch-label">Taisyklė įjungta</span>
                    <AppSwitch
                        :model-value="settingsForm.is_active"
                        :disabled="savingSettings"
                        @update:model-value="handleRuleToggle"
                    />
                </div>
            </div>
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
                            <td class="actions-cell">
                                <AppButton
                                    text="Atkurti"
                                    variant="primary"
                                    as-button
                                    :disabled="restoring === restoringKey('users', u.id)"
                                    @click="restore('users', u)"
                                />
                                <AppButton
                                    text="Naikinti"
                                    variant="danger"
                                    as-button
                                    :disabled="destroying === u.id"
                                    @click="confirmDeleteUser = u"
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
                            <td>{{ p.description_preview || '-' }}</td>
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
        <ConfirmModal
            :open="!!confirmDeleteUser"
            title="Galutinis pašalinimas"
            :message="confirmDeleteUser ? `Ar tikrai norite galutinai pašalinti ${confirmDeleteUser.name} (${confirmDeleteUser.email})? Šio veiksmo atšaukti nebus galima.` : ''"
            confirm-text="Naikinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            :loading="destroying !== null"
            @close="confirmDeleteUser = null"
            @confirm="destroyUser"
        />
    </div>
</template>

<style scoped>
.admin-archive {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Kaip AdminFiles „Failai“ kortelės: apie pusę turinio pločio, ne per visą eilutę */
.settings-card-wrap {
    width: 100%;
    max-width: calc((100% - 20px) / 2);
    min-width: 0;
    box-sizing: border-box;
    margin-bottom: 20px;
}

@media (max-width: 720px) {
    .settings-card-wrap {
        max-width: 100%;
    }
}

.settings-card {
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0;
    margin-bottom: 0;
}
.settings-card__title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 4px 0;
    padding: 0;
    color: #1a1a1a;
}
.settings-card__hint {
    font-size: 14px;
    color: #555;
    margin: 0 0 14px 0;
    padding: 0;
    line-height: 1.35;
}
.settings-card__row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.settings-card__label {
    font-size: 14px;
    color: #333;
}
.settings-card__row .input-narrow {
    width: 100px;
}
.settings-card__switch-row {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
}
.settings-card__switch-label {
    font-size: 14px;
    color: #333;
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 10px 0;
    padding: 0;
    color: #1a1a1a;
}
.tabs {
    display: flex;
    gap: 0;
    margin-bottom: 12px;
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
.actions-cell {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-start;
}
.table :deep(.app-button) {
    width: 100px !important;
    min-width: 100px;
    height: 36px !important;
    min-height: 36px !important;
    font-size: 13px !important;
    padding: 0 12px !important;
    border-radius: 8px !important;
    margin: 0 !important;
}
</style>
