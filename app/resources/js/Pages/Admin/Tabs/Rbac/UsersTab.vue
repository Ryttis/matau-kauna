<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import { ROLE_INSTITUTION_ADMIN, ROLE_EMPLOYEE } from '@/constants/roles.js'

const props = defineProps({
    usersPage: Object,
    roles: Array,
    institutions: { type: Array, default: () => [] },
    saving: Boolean
})

const emit = defineEmits(['refresh', 'set-saving'])

const isCreateOpen = ref(false)
const isEditOpen = ref(false)
const isDeleteOpen = ref(false)
const isAssignRolesOpen = ref(false)
const isInviteOpen = ref(false)
const inviteError = ref('')
const assignRolesError = ref('')

const createForm = ref({ name: '', email: '', password: '', institution_id: '' })
const inviteForm = ref({ email: '', institution_id: '', role_id: '' })
const editForm = ref({ id: null, name: '', email: '', password: '', institution_id: '' })
const deleteTarget = ref({ id: null, name: '', email: '' })

const selectedUser = ref(null)
const selectedRoleIds = ref([])

const openCreate = () => {
    createForm.value = { name: '', email: '', password: '', institution_id: '' }
    isCreateOpen.value = true
}

const openEdit = (u) => {
    editForm.value = { id: u.id, name: u.name, email: u.email, password: '', institution_id: u.institution_id ?? '' }
    isEditOpen.value = true
}

const openDelete = (u) => {
    deleteTarget.value = { id: u.id, name: u.name, email: u.email }
    isDeleteOpen.value = true
}

const openAssignRoles = (u) => {
    selectedUser.value = u
    selectedRoleIds.value = (u.roles ?? []).map(r => r.id)
    assignRolesError.value = ''
    isAssignRolesOpen.value = true
}

const inviteRoles = computed(() =>
    (props.roles ?? []).filter(
        (r) => r.role === ROLE_INSTITUTION_ADMIN || r.role === ROLE_EMPLOYEE
    )
)

const restrictedRoleIds = computed(() =>
    (props.roles ?? [])
        .filter((r) => r.role === ROLE_INSTITUTION_ADMIN || r.role === ROLE_EMPLOYEE)
        .map((r) => r.id)
)

const hasRestrictedRole = (user) =>
    (user?.roles ?? []).some((r) => restrictedRoleIds.value.includes(r.id))

const isRestrictedRole = (role) => restrictedRoleIds.value.includes(role.id)

const assignableRoles = computed(() =>
    (props.roles ?? []).filter((r) => !isRestrictedRole(r))
)

const openInvite = () => {
    inviteForm.value = { email: '', institution_id: '', role_id: '' }
    inviteError.value = ''
    isInviteOpen.value = true
}

const inviteUser = async () => {
    inviteError.value = ''
    emit('set-saving', true)
    try {
        await axios.post('/invitations', inviteForm.value)
        isInviteOpen.value = false
        emit('refresh')
    } catch (e) {
        inviteError.value = e.response?.data?.message ?? 'Nepavyko išsiųsti pakvietimo.'
    } finally {
        emit('set-saving', false)
    }
}

const createUser = async () => {
    emit('set-saving', true)
    try {
        await axios.post('/admin/rbac/users', {
            ...createForm.value,
            institution_id: createForm.value.institution_id || null
        })
        isCreateOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const updateUser = async () => {
    emit('set-saving', true)
    try {
        await axios.put(`/admin/rbac/users/${editForm.value.id}`, {
            name: editForm.value.name,
            email: editForm.value.email,
            password: editForm.value.password || null,
            institution_id: editForm.value.institution_id || null
        })
        isEditOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deleteUser = async () => {
    emit('set-saving', true)
    try {
        await axios.delete(`/admin/rbac/users/${deleteTarget.value.id}`)
        isDeleteOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveUserRoles = async () => {
    if (!selectedUser.value) return

    assignRolesError.value = ''
    const restrictedRoleIds = (props.roles ?? [])
        .filter((r) => r.role === ROLE_INSTITUTION_ADMIN || r.role === ROLE_EMPLOYEE)
        .map((r) => r.id)
    const hasRestrictedRoles = selectedRoleIds.value.some((id) => restrictedRoleIds.includes(id))

    if (hasRestrictedRoles && !selectedUser.value?.institution_id) {
        inviteError.value = 'Įstaigos administratorių ar darbuotojų paskyros turi būti kuriamos per pakvietimą.';
        isAssignRolesOpen.value = false
        return
    }

    emit('set-saving', true)
    try {
        await axios.post(`/admin/rbac/users/${selectedUser.value.id}/roles`, {
            role_ids: selectedRoleIds.value
        })
        isAssignRolesOpen.value = false
        emit('refresh')
    } catch (e) {
        assignRolesError.value = e.response?.data?.message ?? 'Nepavyko išsaugoti rolių.'
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div>
        <div class="header-row">
            <h3 class="title">Naudotojai</h3>
            <div class="header-actions">
                <button type="button" class="secondary-btn small" @click="openInvite">
                    Pakviesti
                </button>
                <button type="button" class="primary-btn small" @click="openCreate">
                    + Pridėti
                </button>
            </div>
        </div>

        <table class="table" v-if="usersPage?.data?.length">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vardas</th>
                    <th>Paštas</th>
                    <th>Įstaiga</th>
                    <th>Rolės</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="u in usersPage.data" :key="u.id">
                    <td>{{ u.id }}</td>
                    <td>{{ u.name }}</td>
                    <td>{{ u.email }}</td>
                    <td>{{ u.institution?.name ?? '—' }}</td>
                    <td>
                        <span v-for="r in (u.roles ?? [])" :key="r.id" class="pill">
                            {{ r.name }}
                        </span>
                    </td>
                    <td class="actions">
                        <button type="button" class="link-btn" @click="openAssignRoles(u)">Rolės</button>
                        <button type="button" class="link-btn" @click="openEdit(u)">Redaguoti</button>
                        <button type="button" class="link-btn danger" @click="openDelete(u)">Trinti</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra naudotojų.</div>

        <!-- Create -->
        <BaseModal :open="isCreateOpen" title="Sukurti naudotoją" @close="isCreateOpen = false">
            <div class="hint">
                Įstaigos administratorių ir darbuotojų paskyros kuriamos tik per pakvietimą.
            </div>
            <label class="field">
                <span>Vardas</span>
                <input class="input" v-model="createForm.name" />
            </label>

            <label class="field">
                <span>Paštas</span>
                <input class="input" v-model="createForm.email" />
            </label>

            <label class="field">
                <span>Slaptažodis</span>
                <input class="input" type="password" v-model="createForm.password" />
            </label>

            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="createForm.institution_id">
                    <option value="">— Nepriskirta</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
            </label>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isCreateOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving" @click="createUser">
                    {{ saving ? 'Kuriama...' : 'Sukurti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Invite -->
        <BaseModal :open="isInviteOpen" title="Pakviesti naudotoją" @close="isInviteOpen = false">
            <div v-if="inviteError" class="error">{{ inviteError }}</div>
            <label class="field">
                <span>El. paštas</span>
                <input class="input" type="email" v-model="inviteForm.email" />
            </label>
            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="inviteForm.institution_id">
                    <option value="">— Pasirinkite</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
            </label>
            <label class="field">
                <span>Rolė</span>
                <select class="input" v-model="inviteForm.role_id">
                    <option value="">— Pasirinkite</option>
                    <option v-for="r in inviteRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
            </label>
            <template #footer>
                <button type="button" class="secondary-btn" @click="isInviteOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving || !inviteForm.email || !inviteForm.institution_id || !inviteForm.role_id" @click="inviteUser">
                    {{ saving ? 'Siunčiama...' : 'Siųsti pakvietimą' }}
                </button>
            </template>
        </BaseModal>

        <!-- Edit -->
        <BaseModal :open="isEditOpen" title="Redaguoti naudotoją" @close="isEditOpen = false">
            <label class="field">
                <span>Vardas</span>
                <input class="input" v-model="editForm.name" />
            </label>

            <label class="field">
                <span>Paštas</span>
                <input class="input" v-model="editForm.email" />
            </label>

            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="editForm.institution_id">
                    <option value="">— Nepriskirta</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
            </label>

            <label class="field">
                <span>Naujas slaptažodis (nebūtina)</span>
                <input class="input" type="password" v-model="editForm.password" />
            </label>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isEditOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving" @click="updateUser">
                    {{ saving ? 'Saugoma...' : 'Išsaugoti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Assign roles (moved from right panel) -->
        <BaseModal
            :open="isAssignRolesOpen"
            title="Priskirti roles"
            width="640px"
            @close="isAssignRolesOpen = false"
        >
            <div v-if="assignRolesError" class="error">{{ assignRolesError }}</div>
            <div v-if="hasRestrictedRole(selectedUser) && !selectedUser?.institution_id" class="error">
                Įstaigos administratorių ar darbuotojų paskyros turi būti kuriamos per pakvietimą.
            </div>
            <div class="hint">
                Naudotojas: <strong>{{ selectedUser?.name }}</strong>
            </div>
            <div class="hint">
                Įstaigos administratorių ir darbuotojų rolės priskiriamos tik per pakvietimą.
            </div>

            <div class="checklist">
                <label v-for="r in assignableRoles" :key="r.id" class="check">
                    <input
                        type="checkbox"
                        :value="r.id"
                        v-model="selectedRoleIds"
                    />
                    <span>
                        {{ r.name }} ({{ r.role }})
                    </span>
                </label>
            </div>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isAssignRolesOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving" @click="saveUserRoles">
                    {{ saving ? 'Saugoma...' : 'Išsaugoti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Delete -->
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
.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 13px;
}

.title {
    font-size: 18px;
    font-weight: 700;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #eef0f3;
    font-size: 13px;
    vertical-align: top;
}

.pill {
    display: inline-block;
    margin-right: 6px;
    margin-bottom: 4px;
    padding: 4px 8px;
    border-radius: 999px;
    background: #F6F7F9;
    color: #1946A0;
    font-size: 12px;
}

.actions {
    width: 240px;
}

.link-btn {
    border: none;
    background: transparent;
    color: #1946A0;
    cursor: pointer;
    padding: 0;
    font-weight: 600;
    margin-right: 10px;
}

.link-btn:hover {
    text-decoration: underline;
}

.link-btn.danger {
    color: #b42318;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
    font-size: 13px;
}

.input {
    height: 40px;
    border: 1px solid #e6e8ee;
    border-radius: 10px;
    padding: 0 12px;
    background: #F6F7F9;
}

.hint {
    color: #6b7280;
    font-size: 13px;
    background: #F6F7F9;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.checklist {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 420px;
    overflow: auto;
    border-top: 1px solid #eef0f3;
    border-bottom: 1px solid #eef0f3;
    padding: 12px 0;
}

.check {
    display: flex;
    gap: 10px;
    align-items: center;
    font-size: 13px;
}

.primary-btn {
    border: none;
    border-radius: 10px;
    padding: 10px 12px;
    background: #1946A0;
    color: #ffffff;
    cursor: pointer;
    font-weight: 600;
}

.primary-btn.small {
    padding: 10px 14px;
}

.primary-btn.fixed {
    padding: 10px 14px;
}

.primary-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.secondary-btn {
    border: 1px solid #e6e8ee;
    background: #ffffff;
    color: #1946A0;
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    font-weight: 600;
}

.secondary-btn.small {
    padding: 8px 14px;
    font-size: 13px;
}
</style>
