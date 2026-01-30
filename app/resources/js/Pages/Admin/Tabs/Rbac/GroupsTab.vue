<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const props = defineProps({
    groups: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Array,
        default: () => []
    },
    saving: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['refresh', 'set-saving'])

const isCreateOpen = ref(false)
const isDeleteOpen = ref(false)
const isAssignPermissionsOpen = ref(false)

const createForm = ref({ name: '' })
const deleteTarget = ref({ id: null, name: '' })

const selectedGroup = ref(null)
const selectedPermissionIds = ref([])

const openCreate = () => {
    createForm.value = { name: '' }
    isCreateOpen.value = true
}

const openDelete = (g) => {
    deleteTarget.value = { id: g.id, name: g.name }
    isDeleteOpen.value = true
}

const openAssignPermissions = (g) => {
    selectedGroup.value = g
    selectedPermissionIds.value = (g.permissions ?? []).map(p => p.id)
    isAssignPermissionsOpen.value = true
}

const createGroup = async () => {
    emit('set-saving', true)

    try {
        await axios.post('/admin/rbac/groups', createForm.value)
        isCreateOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deleteGroup = async () => {
    emit('set-saving', true)

    try {
        await axios.delete(`/admin/rbac/groups/${deleteTarget.value.id}`)
        isDeleteOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveGroupPermissions = async () => {
    if (!selectedGroup.value) return

    emit('set-saving', true)

    try {
        await axios.post(`/admin/rbac/groups/${selectedGroup.value.id}/permissions`, {
            permission_ids: selectedPermissionIds.value
        })

        isAssignPermissionsOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div>
        <div class="header-row">
            <h3 class="title">Grupės</h3>
            <button type="button" class="primary-btn small" @click="openCreate">
                + Pridėti
            </button>
        </div>

        <table class="table" v-if="groups.length">
            <thead>
                <tr>
                    <th>Pavadinimas</th>
                    <th>Teisės</th>
                    <th>Rolės</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="g in groups" :key="g.id">
                    <td>{{ g.name }}</td>
                    <td>
                        <span v-for="p in (g.permissions ?? [])" :key="p.id" class="pill">
                            {{ p.permission }}
                        </span>
                    </td>
                    <td>
                        <span v-for="r in (g.roles ?? [])" :key="r.id" class="pill">
                            {{ r.name }}
                        </span>
                    </td>
                    <td class="actions">
                        <button type="button" class="link-btn" @click="openAssignPermissions(g)">
                            Teisės
                        </button>
                        <button type="button" class="link-btn danger" @click="openDelete(g)">
                            Trinti
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra grupių.</div>

        <!-- Create Group -->
        <BaseModal :open="isCreateOpen" title="Sukurti grupę" @close="isCreateOpen = false">
            <label class="field">
                <span>Pavadinimas</span>
                <input class="input" placeholder="Users" v-model="createForm.name" />
            </label>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isCreateOpen = false">
                    Atšaukti
                </button>

                <button type="button" class="primary-btn fixed" :disabled="saving" @click="createGroup">
                    {{ saving ? 'Kuriama...' : 'Sukurti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Assign Permissions -->
        <BaseModal
            :open="isAssignPermissionsOpen"
            title="Priskirti teises grupei"
            width="720px"
            @close="isAssignPermissionsOpen = false"
        >
            <div class="hint">
                Grupė: <strong>{{ selectedGroup?.name }}</strong>
            </div>

            <div class="checklist">
                <label v-for="p in permissions" :key="p.id" class="check">
                    <input type="checkbox" :value="p.id" v-model="selectedPermissionIds" />
                    <span>{{ p.permission }}</span>
                </label>
            </div>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isAssignPermissionsOpen = false">
                    Atšaukti
                </button>

                <button type="button" class="primary-btn fixed" :disabled="saving" @click="saveGroupPermissions">
                    {{ saving ? 'Saugoma...' : 'Išsaugoti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Delete Group -->
        <ConfirmModal
            :open="isDeleteOpen"
            title="Ištrinti grupę"
            :message="`Ar tikrai norite ištrinti grupę ${deleteTarget.name}?`"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            @close="isDeleteOpen = false"
            @confirm="deleteGroup"
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
    width: 170px;
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
