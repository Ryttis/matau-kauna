<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import ModalFooterActions from '@/Components/App/ModalFooterActions.vue'
import InstitutionTeamMembersModal from '@/Components/InstitutionTeams/InstitutionTeamMembersModal.vue'
import { adminApi } from '@/constants/api/adminApi'
import { settingsApi } from '@/constants/api/settingsApi'
import { useSuccessModal } from '@/composables/useSuccessModal'

const props = defineProps({
    initialInstitutionId: {
        type: [Number, String],
        default: null,
    },
})

const page = usePage()
const isSystemAdmin = computed(() => (page.props.auth?.permissions ?? []).includes('admin.access'))
const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const institutions = ref([])
const selectedInstitutionId = ref(props.initialInstitutionId ? Number(props.initialInstitutionId) : null)

const employees = ref([])
const teams = ref([])

const isCreateOpen = ref(false)
const createForm = ref({ name: '' })

const isMembersOpen = ref(false)
const selectedTeam = ref(null)

const isDeleteOpen = ref(false)
const deleteTarget = ref(null)

const loadInstitutions = async () => {
    const { data } = await axios.get(adminApi.institutionsList)
    institutions.value = data || []

    if (!selectedInstitutionId.value && institutions.value.length) {
        selectedInstitutionId.value = Number(institutions.value[0].id)
    }
}

const loadTeams = async () => {
    if (!selectedInstitutionId.value && isSystemAdmin.value) {
        teams.value = []
        employees.value = []
        return
    }

    loading.value = true
    errorMessage.value = ''

    try {
        const params = {}
        if (isSystemAdmin.value && selectedInstitutionId.value) {
            params.institution_id = selectedInstitutionId.value
        }

        const { data } = await axios.get(settingsApi.teams, { params })
        employees.value = data?.employees ?? []
        teams.value = data?.teams ?? []
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko užkrauti komandų.'
    } finally {
        loading.value = false
    }
}

const onInstitutionChange = async () => {
    if (!isSystemAdmin.value) return
    await loadTeams()
}

const openCreate = () => {
    createForm.value = { name: '' }
    isCreateOpen.value = true
}

const createTeam = async () => {
    if (saving.value) return
    saving.value = true

    try {
        const payload = isSystemAdmin.value
            ? { name: createForm.value.name, institution_id: selectedInstitutionId.value }
            : { name: createForm.value.name }

        await axios.post(settingsApi.teams, payload)
        isCreateOpen.value = false
        showWithMessage('Komanda sukurta.')
        await loadTeams()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko sukurti komandos.'
    } finally {
        saving.value = false
    }
}

const openMembersModal = (team) => {
    selectedTeam.value = team
    isMembersOpen.value = true
}

const closeMembersModal = () => {
    isMembersOpen.value = false
    selectedTeam.value = null
}

const onMembersSaved = (data) => {
    teams.value = teams.value.map((t) => (t.id === data.id ? data : t))
    showWithMessage('Komandos nariai atnaujinti.')
}

const openDelete = (team) => {
    deleteTarget.value = team
    isDeleteOpen.value = true
}

const deleteTeam = async () => {
    if (!deleteTarget.value || saving.value) return
    saving.value = true

    try {
        await axios.delete(settingsApi.team(deleteTarget.value.id))
        isDeleteOpen.value = false
        deleteTarget.value = null
        showWithMessage('Komanda pašalinta.')
        await loadTeams()
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko pašalinti komandos.'
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    loading.value = true
    try {
        if (isSystemAdmin.value) {
            await loadInstitutions()
        }
        await loadTeams()
    } finally {
        loading.value = false
    }
})

const canRender = computed(() => {
    if (isSystemAdmin.value) return !!selectedInstitutionId.value || institutions.value.length === 0
    return true
})
</script>

<template>
    <div class="admin-tab institution-teams-manager" v-if="canRender">
        <SuccessModal :show="showSuccessModal" :message="successMessage" @close="closeSuccessModal" />

        <div class="teams-heading">
            <h3 class="title">Komandos (grupės)</h3>
        </div>
        <p class="page-desc">Kurkite komandas ir priskirkite įstaigos darbuotojus problemoms spręsti grupėmis.</p>

        <div v-if="errorMessage" class="error">{{ errorMessage }}</div>

        <div v-if="isSystemAdmin" class="institution-filter">
            <label class="field-label" for="institution-select">Įstaiga</label>
            <select
                id="institution-select"
                class="input"
                v-model="selectedInstitutionId"
                @change="onInstitutionChange"
            >
                <option v-for="inst in institutions" :key="inst.id" :value="inst.id">{{ inst.name }}</option>
            </select>
        </div>

        <div v-if="loading" class="loading">Kraunama...</div>

        <template v-else>
            <div class="header-row">
                <h4 class="subsection-title">Įstaigos komandos</h4>
                <div class="header-actions">
                    <AppButton
                        text="+ Kurti"
                        variant="secondary"
                        as-button
                        class="btn-header"
                        @click="openCreate"
                    />
                </div>
            </div>

            <table v-if="teams.length" class="table">
                <thead>
                    <tr>
                        <th>Komanda</th>
                        <th>Nariai</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in teams" :key="t.id">
                        <td>{{ t.name }}</td>
                        <td>
                            {{ (t.employees ?? []).length }}
                        </td>
                        <td class="actions">
                            <span class="actions-row">
                                <button type="button" class="link-btn" @click="openMembersModal(t)">Nariai</button>
                                <span class="actions-separator" aria-hidden="true">|</span>
                                <button type="button" class="link-btn link-btn--danger" @click="openDelete(t)">
                                    Trinti
                                </button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-else class="empty">Nėra komandų. Sukurkite pirmąją.</p>
        </template>

        <BaseModal :open="isCreateOpen" title="Kurti komandą" max-width="640px" @close="isCreateOpen = false">
            <div class="form-grid">
                <AppInput v-model="createForm.name" label="Komandos pavadinimas *" />
            </div>

            <template #footer>
                <ModalFooterActions
                    cancel-text="Atšaukti"
                    primary-text="Sukurti"
                    :primary-disabled="saving || !createForm.name?.trim()"
                    @cancel="isCreateOpen = false"
                    @primary="createTeam"
                />
            </template>
        </BaseModal>

        <InstitutionTeamMembersModal
            :open="isMembersOpen"
            :team="selectedTeam"
            :institution-employees="employees"
            @close="closeMembersModal"
            @saved="onMembersSaved"
        />

        <ConfirmModal
            :open="isDeleteOpen"
            title="Ištrinti komandą"
            :message="deleteTarget ? `Ar tikrai norite ištrinti komandą '${deleteTarget.name}'?` : ''"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            @close="isDeleteOpen = false"
            @confirm="deleteTeam"
        />
    </div>
</template>

<style scoped>
.institution-teams-manager {
    width: 100%;
    max-width: 100%;
}

.teams-heading {
    margin-bottom: 0;
}

.title {
    margin: 0;
    padding: 0;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.35;
}

.subsection-title {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    color: #1a1a1a;
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.loading {
    padding: 12px 0;
}

.empty {
    padding: 14px 0;
    color: var(--color-text-muted, #333);
}

.institution-filter {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
}

.field-label {
    font-size: 14px;
    font-weight: 600;
}

.actions-row {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.actions-separator {
    color: var(--color-gray-400);
}

.link-btn {
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 13px;
    color: var(--color-primary);
    cursor: pointer;
}

.link-btn:hover {
    text-decoration: none;
}

.link-btn--danger {
    color: #c62828;
}

.link-btn--danger:hover {
    color: var(--color-primary-hover);
}
</style>

