<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import { adminRbacApi } from '@/constants/api/adminRbacApi'
import { invitationsApi } from '@/constants/api/invitationsApi'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { ROLE_INSTITUTION_ADMIN, ROLE_EMPLOYEE, ROLE_REPORTER } from '@/constants/domain/roles'

const props = defineProps({
    usersPage: Object,
    roles: Array,
    institutions: { type: Array, default: () => [] },
    saving: Boolean
})

const emit = defineEmits(['refresh', 'set-saving', 'show-success', 'filter'])

const filters = ref({ search: '', role_id: '', institution_id: '' })

let searchDebounce = null
watch(() => filters.value.search, () => {
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        emit('filter', { ...filters.value })
    }, 300)
})

watch([() => filters.value.role_id, () => filters.value.institution_id], () => {
    emit('filter', { ...filters.value })
})

const hasActiveFilters = computed(
    () => !!(filters.value.search || filters.value.role_id || filters.value.institution_id)
)

const clearFilters = () => {
    filters.value = { search: '', role_id: '', institution_id: '' }
}

const isCreateOpen = ref(false)
const isEditOpen = ref(false)
const isDeleteOpen = ref(false)
const isAssignRolesOpen = ref(false)
const isInviteOpen = ref(false)
const inviteError = ref('')
const assignRolesError = ref('')
const createError = ref('')
const createErrors = ref([])
const editError = ref('')
const editErrors = ref([])

const createForm = ref({ name: '', surname: '', email: '', password: '', password_confirmation: '', institution_id: '', role_id: '' })
const inviteForm = ref({ email: '', institution_id: '', role_id: '' })
const editForm = ref({ id: null, name: '', surname: '', email: '', password: '', institution_id: '' })
const deleteTarget = ref({ id: null, name: '', email: '' })

const selectedUser = ref(null)
const selectedRoleIds = ref([])

const displayUserName = (user) => [user?.name, user?.surname].filter(Boolean).join(' ').trim()

const formatTermsAcceptance = (user) => {
    if (!user?.terms_accepted_at) return '-'
    const date = new Date(user.terms_accepted_at)
    if (Number.isNaN(date.getTime())) return '-'
    return date.toLocaleString('lt-LT', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatTermsVersion = (user) => {
    const version = user?.accepted_terms_version?.version
    return version ? `v${version}` : '-'
}

const openCreate = () => {
    createForm.value = { name: '', surname: '', email: '', password: '', password_confirmation: '', institution_id: '', role_id: '' }
    createError.value = ''
    createErrors.value = []
    isCreateOpen.value = true
}

const openEdit = (u) => {
    editForm.value = { id: u.id, name: displayUserName(u) || u.name, surname: '', email: u.email, password: '', password_confirmation: '', institution_id: u.institution_id ?? '' }
    editError.value = ''
    editErrors.value = []
    isEditOpen.value = true
}

const openDelete = (u) => {
    deleteTarget.value = { id: u.id, name: u.name, email: u.email }
    isDeleteOpen.value = true
}

const openAssignRoles = (u) => {
    selectedUser.value = u
    let ids = (u.roles ?? []).map((r) => r.id)
    const rows = (u.roles ?? []).filter(Boolean)
    const hasReporter = rows.some((row) => row.role === ROLE_REPORTER)
    const hasStaff = rows.some((row) => row.role !== ROLE_REPORTER)
    if (hasReporter && hasStaff) {
        ids = rows.filter((row) => row.role === ROLE_REPORTER).map((row) => row.id)
    }
    selectedRoleIds.value = ids
    assignRolesError.value = ''
    isAssignRolesOpen.value = true
}

const inviteRoles = computed(() =>
    (props.roles ?? []).filter(
        (r) => r.role === ROLE_INSTITUTION_ADMIN || r.role === ROLE_EMPLOYEE
    )
)

const selectedInviteInstitution = computed(() =>
    (props.institutions ?? []).find(
        (inst) => String(inst.id) === String(inviteForm.value.institution_id)
    )
)

const isInviteInstitutionEmailOnly = computed(() => !!selectedInviteInstitution.value?.is_email_only)

const assignableRoles = computed(() => props.roles ?? [])

const findAssignableRole = (roleId) =>
    assignableRoles.value.find((x) => String(x.id) === String(roleId))

const isAssignRoleDisabled = (r) => {
    const selected = selectedRoleIds.value
    const hasReporter = selected.some((id) => findAssignableRole(id)?.role === ROLE_REPORTER)
    const hasNonReporter = selected.some((id) => findAssignableRole(id)?.role !== ROLE_REPORTER)
    if (hasReporter) {
        return r.role !== ROLE_REPORTER
    }
    if (hasNonReporter) {
        return r.role === ROLE_REPORTER
    }
    return false
}

const onToggleAssignRole = (r, checked) => {
    if (r.role === ROLE_REPORTER && checked) {
        selectedRoleIds.value = [r.id]
        return
    }
    if (!checked) {
        selectedRoleIds.value = selectedRoleIds.value.filter((id) => String(id) !== String(r.id))
        return
    }
    selectedRoleIds.value = [
        ...selectedRoleIds.value.filter((id) => findAssignableRole(id)?.role !== ROLE_REPORTER),
        r.id,
    ]
}

const openInvite = () => {
    inviteForm.value = { email: '', institution_id: '', role_id: '' }
    inviteError.value = ''
    isInviteOpen.value = true
}

const inviteUser = async () => {
    inviteError.value = ''
    if (isInviteInstitutionEmailOnly.value) {
        inviteError.value = 'Ši įstaiga pažymėta kaip el. pašto integracija, todėl pakvietimai negalimi.';
        return
    }
    emit('set-saving', true)
    try {
        await axios.post(invitationsApi.store, inviteForm.value)
        isInviteOpen.value = false
        emit('show-success', 'Pakvietimas išsiųstas.')
        emit('refresh')
    } catch (e) {
        inviteError.value = e.response?.data?.message ?? 'Nepavyko išsiųsti pakvietimo.'
    } finally {
        emit('set-saving', false)
    }
}

const createUser = async () => {
    createError.value = ''
    emit('set-saving', true)
    try {
        await axios.post(adminRbacApi.users, {
            name: createForm.value.name,
            surname: null,
            email: createForm.value.email,
            password: createForm.value.password,
            password_confirmation: createForm.value.password_confirmation,
            institution_id: createForm.value.institution_id || null,
            role_id: createForm.value.role_id || null
        })
        isCreateOpen.value = false
        emit('show-success', 'Naudotojas sukurtas.')
        emit('refresh')
    } catch (e) {
        const errors = e.response?.data?.errors
        if (errors && typeof errors === 'object') {
            const list = [...new Set(Object.values(errors).flat().filter(Boolean))]
            createErrors.value = list.length ? list : [e.response?.data?.message || 'Nepavyko sukurti naudotojo.']
            createError.value = createErrors.value.length === 1 ? createErrors.value[0] : ''
        } else {
            createError.value = e.response?.data?.message || 'Nepavyko sukurti naudotojo.'
            createErrors.value = createError.value ? [createError.value] : []
        }
    } finally {
        emit('set-saving', false)
    }
}

const updateUser = async () => {
    editError.value = ''
    editErrors.value = []
    emit('set-saving', true)
    try {
        const payload = {
            name: editForm.value.name,
            surname: null,
            email: editForm.value.email,
            institution_id: editForm.value.institution_id || null
        }
        if (editForm.value.password) {
            payload.password = editForm.value.password
            payload.password_confirmation = editForm.value.password_confirmation
        }
        await axios.put(adminRbacApi.user(editForm.value.id), payload)
        isEditOpen.value = false
        emit('show-success', 'Naudotojas atnaujintas.')
        emit('refresh')
    } catch (e) {
        const errors = e.response?.data?.errors
        if (errors && typeof errors === 'object') {
            const list = [...new Set(Object.values(errors).flat().filter(Boolean))]
            editErrors.value = list.length ? list : [e.response?.data?.message || 'Nepavyko atnaujinti naudotojo.']
            editError.value = editErrors.value.length === 1 ? editErrors.value[0] : ''
        } else {
            editError.value = e.response?.data?.message || 'Nepavyko atnaujinti naudotojo.'
            editErrors.value = editError.value ? [editError.value] : []
        }
    } finally {
        emit('set-saving', false)
    }
}

const deleteUser = async () => {
    emit('set-saving', true)
    try {
        await axios.delete(adminRbacApi.user(deleteTarget.value.id))
        isDeleteOpen.value = false
        emit('show-success', 'Naudotojas pašalintas.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveUserRoles = async () => {
    if (!selectedUser.value) return

    const ids = selectedRoleIds.value.filter((id) => id != null && String(id).trim() !== '')
    if (ids.length === 0) {
        assignRolesError.value = 'Pasirinkite bent vieną rolę.'
        return
    }

    assignRolesError.value = ''

    emit('set-saving', true)
    try {
        await axios.post(adminRbacApi.userRoles(selectedUser.value.id), {
            role_ids: ids,
        })
        isAssignRolesOpen.value = false
        emit('show-success', 'Naudotojo rolės atnaujintos.')
        emit('refresh')
    } catch (e) {
        const errors = e.response?.data?.errors
        if (errors && typeof errors === 'object') {
            const flat = Object.values(errors).flat().filter(Boolean)
            assignRolesError.value = flat[0] ?? e.response?.data?.message ?? 'Nepavyko išsaugoti rolių.'
        } else {
            assignRolesError.value = e.response?.data?.message ?? 'Nepavyko išsaugoti rolių.'
        }
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">Naudotojai</h3>
            <div class="header-actions">
                <AppButton text="Pakviesti" variant="secondary" as-button class="btn-header" @click="openInvite" />
                <AppButton text="+ Pridėti" variant="primary" as-button class="btn-header" @click="openCreate" />
            </div>
        </div>

        <div class="filters">
            <input
                v-model="filters.search"
                type="text"
                class="input filter-search"
                placeholder="Vardas, pavardė arba el. paštas"
            />
            <select v-model="filters.role_id" class="input filter-select">
                <option value="">Visos rolės</option>
                <option v-for="r in (roles ?? [])" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <select v-model="filters.institution_id" class="input filter-select">
                <option value="">Visos įstaigos</option>
                <option value="none">Be įstaigos</option>
                <option v-for="i in (institutions ?? [])" :key="i.id" :value="i.id">{{ i.name }}</option>
            </select>
            <button v-if="hasActiveFilters" type="button" class="link-btn" @click="clearFilters">
                Išvalyti filtrus
            </button>
        </div>

        <table class="table" v-if="usersPage?.data?.length">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Asmuo</th>
                    <th>Paštas</th>
                    <th>Įstaiga</th>
                    <th>Rolės</th>
                    <th>Taisyklių sutikimas</th>
                    <th>Versija</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="u in usersPage.data" :key="u.id">
                    <td>{{ u.id }}</td>
                    <td>{{ displayUserName(u) || '-' }}</td>
                    <td>{{ u.email }}</td>
                    <td>{{ u.institution?.name ?? '-' }}</td>
                    <td>
                        <span v-for="r in (u.roles ?? [])" :key="r.id" class="pill">
                            {{ r.name }}
                        </span>
                    </td>
                    <td>{{ formatTermsAcceptance(u) }}</td>
                    <td>{{ formatTermsVersion(u) }}</td>
                    <td class="actions"><span class="actions-row"><button type="button" class="link-btn" @click="openAssignRoles(u)">Rolės</button><button type="button" class="link-btn" @click="openEdit(u)">Redaguoti</button><button type="button" class="link-btn danger" @click="openDelete(u)">Trinti</button></span></td>
                </tr>
            </tbody>
        </table>

        <div v-else>{{ hasActiveFilters ? 'Pagal pasirinktus filtrus naudotojų nerasta.' : 'Nėra naudotojų.' }}</div>

        <BaseModal :open="isCreateOpen" title="Sukurti naudotoją" @close="isCreateOpen = false">
            <div v-if="createErrors.length" class="error">
                <ul v-if="createErrors.length > 1" class="error-list">
                    <li v-for="(err, i) in createErrors" :key="i">{{ err }}</li>
                </ul>
                <span v-else>{{ createErrors[0] }}</span>
            </div>
            <label class="field">
                <span>Vardas Pavardė</span>
                <input class="input" v-model="createForm.name" />
            </label>

            <label class="field">
                <span>Paštas</span>
                <input class="input" v-model="createForm.email" />
            </label>

            <label class="field">
                <span>Slaptažodis</span>
                <input class="input" type="password" v-model="createForm.password" placeholder="Min. 8 simb., didž./maž. raidė, specialus" />
            </label>
            <label class="field">
                <span>Pakartokite slaptažodį</span>
                <input class="input" type="password" v-model="createForm.password_confirmation" />
            </label>

            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="createForm.institution_id">
                    <option value="">- Nepriskirta</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
            </label>

            <label class="field">
                <span>Rolė</span>
                <select class="input" v-model="createForm.role_id">
                    <option value="">- Pasirinkite</option>
                    <option v-for="r in assignableRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
            </label>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isCreateOpen = false" />
                <AppButton :text="saving ? 'Kuriama...' : 'Sukurti'" variant="primary" as-button :disabled="saving" @click="createUser" />
            </template>
        </BaseModal>

        <BaseModal :open="isInviteOpen" title="Pakviesti naudotoją" @close="isInviteOpen = false">
            <div v-if="inviteError" class="error">{{ inviteError }}</div>
            <label class="field">
                <span>El. paštas</span>
                <input class="input" type="email" v-model="inviteForm.email" />
            </label>
            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="inviteForm.institution_id">
                    <option value="">- Pasirinkite</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id" :disabled="i.is_email_only">
                        {{ i.name }}{{ i.is_email_only ? ' (tik el. paštu)' : '' }}
                    </option>
                </select>
            </label>
            <div v-if="isInviteInstitutionEmailOnly" class="hint">
                Ši įstaiga yra tik el. pašto integracija, todėl pakvietimai nesiunčiami.
            </div>
            <label class="field">
                <span>Rolė</span>
                <select class="input" v-model="inviteForm.role_id">
                    <option value="">- Pasirinkite</option>
                    <option v-for="r in inviteRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
            </label>
            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isInviteOpen = false" />
                <AppButton
                    :text="saving ? 'Siunčiama...' : 'Siųsti pakvietimą'"
                    variant="primary"
                    as-button
                    :disabled="saving || !inviteForm.email || !inviteForm.institution_id || !inviteForm.role_id || isInviteInstitutionEmailOnly"
                    @click="inviteUser"
                />
            </template>
        </BaseModal>

        <BaseModal :open="isEditOpen" title="Redaguoti naudotoją" @close="isEditOpen = false">
            <div v-if="editErrors.length" class="error">
                <ul v-if="editErrors.length > 1" class="error-list">
                    <li v-for="(err, i) in editErrors" :key="i">{{ err }}</li>
                </ul>
                <span v-else>{{ editErrors[0] }}</span>
            </div>
            <label class="field">
                <span>Vardas Pavardė</span>
                <input class="input" v-model="editForm.name" />
            </label>

            <label class="field">
                <span>Paštas</span>
                <input class="input" v-model="editForm.email" />
            </label>

            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="editForm.institution_id">
                    <option value="">- Nepriskirta</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
            </label>

            <label class="field">
                <span>Naujas slaptažodis (nebūtina)</span>
                <input class="input" type="password" v-model="editForm.password" placeholder="Palikite tuščią, jei nekeičiate" />
            </label>
            <label class="field" v-if="editForm.password">
                <span>Pakartokite slaptažodį</span>
                <input class="input" type="password" v-model="editForm.password_confirmation" />
            </label>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isEditOpen = false" />
                <AppButton text="Išsaugoti" variant="primary" as-button :disabled="saving" @click="updateUser" />
            </template>
        </BaseModal>

        <BaseModal
            :open="isAssignRolesOpen"
            title="Priskirti roles"
            width="640px"
            @close="isAssignRolesOpen = false"
        >
            <div v-if="assignRolesError" class="error">{{ assignRolesError }}</div>
            <div class="hint">
                Naudotojas: <strong>{{ selectedUser?.name }}</strong>
            </div>

            <div class="checklist">
                <label
                    v-for="r in assignableRoles"
                    :key="r.id"
                    class="check"
                    :class="{ 'check--disabled': isAssignRoleDisabled(r) }"
                >
                    <input
                        type="checkbox"
                        :checked="selectedRoleIds.some((id) => String(id) === String(r.id))"
                        :disabled="isAssignRoleDisabled(r)"
                        @change="onToggleAssignRole(r, $event.target.checked)"
                    />
                    <span>
                        {{ r.name }} ({{ r.role }})
                    </span>
                </label>
            </div>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isAssignRolesOpen = false" />
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="saving || selectedRoleIds.length === 0"
                    @click="saveUserRoles"
                />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="isDeleteOpen"
            title="Ištrinti naudotoją"
            :message="`Ar tikrai norite ištrinti ${deleteTarget.name} (${deleteTarget.email})?`"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            @close="isDeleteOpen = false"
            @confirm="deleteUser"
        />
    </div>
</template>

<style scoped>
.filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.filter-search {
    flex: 1 1 240px;
    min-width: 200px;
}

.filter-select {
    flex: 0 1 200px;
    min-width: 160px;
}
</style>
