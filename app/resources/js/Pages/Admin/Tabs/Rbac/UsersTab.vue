<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const props = defineProps({
    usersPage: Object,
    roles: Array,
    saving: Boolean
})

const emit = defineEmits(['refresh', 'set-saving'])

const isCreateOpen = ref(false)
const isEditOpen = ref(false)
const isDeleteOpen = ref(false)
const isAssignRolesOpen = ref(false)

const createForm = ref({ name: '', email: '', password: '' })
const editForm = ref({ id: null, name: '', email: '', password: '' })
const deleteTarget = ref({ id: null, name: '', email: '' })

const selectedUser = ref(null)
const selectedRoleIds = ref([])

const openCreate = () => {
    createForm.value = { name: '', email: '', password: '' }
    isCreateOpen.value = true
}

const openEdit = (u) => {
    editForm.value = { id: u.id, name: u.name, email: u.email, password: '' }
    isEditOpen.value = true
}

const openDelete = (u) => {
    deleteTarget.value = { id: u.id, name: u.name, email: u.email }
    isDeleteOpen.value = true
}

const openAssignRoles = (u) => {
    selectedUser.value = u
    selectedRoleIds.value = (u.roles ?? []).map(r => r.id)
    isAssignRolesOpen.value = true
}

const createUser = async () => {
    emit('set-saving', true)
    try {
        await axios.post('/admin/rbac/users', createForm.value)
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
            password: editForm.value.password || null
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

    emit('set-saving', true)
    try {
        await axios.post(`/admin/rbac/users/${selectedUser.value.id}/roles`, {
            role_ids: selectedRoleIds.value
        })
        isAssignRolesOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div>
        <div class="header-row">
            <h3 class="title">Naudotojai</h3>
            <button type="button" class="primary-btn small" @click="openCreate">
                + Pridėti
            </button>
        </div>

        <table class="table" v-if="usersPage?.data?.length">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Vardas</th>
                    <th>Email</th>
                    <th>Rolės</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="u in usersPage.data" :key="u.id">
                    <td>{{ u.id }}</td>
                    <td>{{ u.name }}</td>
                    <td>{{ u.email }}</td>
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
            <label class="field">
                <span>Vardas</span>
                <input class="input" v-model="createForm.name" />
            </label>

            <label class="field">
                <span>Email</span>
                <input class="input" v-model="createForm.email" />
            </label>

            <label class="field">
                <span>Slaptažodis</span>
                <input class="input" type="password" v-model="createForm.password" />
            </label>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isCreateOpen = false">Atšaukti</button>
                <button type="button" class="primary-btn fixed" :disabled="saving" @click="createUser">
                    {{ saving ? 'Kuriama...' : 'Sukurti' }}
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
                <span>Email</span>
                <input class="input" v-model="editForm.email" />
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
            <div class="hint">
                Naudotojas: <strong>{{ selectedUser?.name }}</strong>
            </div>

            <div class="checklist">
                <label v-for="r in roles" :key="r.id" class="check">
                    <input type="checkbox" :value="r.id" v-model="selectedRoleIds" />
                    <span>{{ r.name }} ({{ r.role }})</span>
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
</style>
