<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const props = defineProps({
    roles: {
        type: Array,
        default: () => []
    },
    groups: {
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
const isAssignGroupsOpen = ref(false)

const createForm = ref({ role: '', name: '' })
const deleteTarget = ref({ id: null, name: '', role: '' })

const selectedRole = ref(null)
const selectedGroupIds = ref([])

const openCreate = () => {
    createForm.value = { role: '', name: '' }
    isCreateOpen.value = true
}

const openDelete = (r) => {
    deleteTarget.value = { id: r.id, name: r.name, role: r.role }
    isDeleteOpen.value = true
}

const openAssignGroups = (r) => {
    selectedRole.value = r
    selectedGroupIds.value = (r.permission_groups ?? []).map(g => g.id)
    isAssignGroupsOpen.value = true
}

const createRole = async () => {
    emit('set-saving', true)

    try {
        await axios.post('/admin/rbac/roles', createForm.value)
        isCreateOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deleteRole = async () => {
    emit('set-saving', true)

    try {
        await axios.delete(`/admin/rbac/roles/${deleteTarget.value.id}`)
        isDeleteOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveRoleGroups = async () => {
    if (!selectedRole.value) return

    emit('set-saving', true)

    try {
        await axios.post(`/admin/rbac/roles/${selectedRole.value.id}/groups`, {
            group_ids: selectedGroupIds.value
        })

        isAssignGroupsOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div>
        <div class="header-row">
            <h3 class="title">Rolės</h3>
            <button type="button" class="primary-btn small" @click="openCreate">
                + Pridėti
            </button>
        </div>

        <table class="table" v-if="roles.length">
            <thead>
                <tr>
                    <th>Pavadinimas</th>
                    <th>Rolės kodas</th>
                    <th>Grupės</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="r in roles" :key="r.id">
                    <td>{{ r.name }}</td>
                    <td>{{ r.role }}</td>
                    <td>
                        <span v-for="g in (r.permission_groups ?? [])" :key="g.id" class="pill">
                            {{ g.name }}
                        </span>
                    </td>
                    <td class="actions">
                        <button type="button" class="link-btn" @click="openAssignGroups(r)">
                            Grupės
                        </button>
                        <button type="button" class="link-btn danger" @click="openDelete(r)">
                            Trinti
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra rolių.</div>

        <!-- Create Role -->
        <BaseModal :open="isCreateOpen" title="Sukurti rolę" @close="isCreateOpen = false">
            <label class="field">
                <span>Rolės kodas</span>
                <input class="input" placeholder="ROLE_MODERATOR" v-model="createForm.role" />
            </label>

            <label class="field">
                <span>Pavadinimas</span>
                <input class="input" placeholder="Moderatorius" v-model="createForm.name" />
            </label>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isCreateOpen = false">
                    Atšaukti
                </button>

                <button type="button" class="primary-btn fixed" :disabled="saving" @click="createRole">
                    {{ saving ? 'Kuriama...' : 'Sukurti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Assign Groups -->
        <BaseModal
            :open="isAssignGroupsOpen"
            title="Priskirti grupes rolei"
            width="640px"
            @close="isAssignGroupsOpen = false"
        >
            <div class="hint">
                Rolė: <strong>{{ selectedRole?.name }}</strong>
            </div>

            <div class="checklist">
                <label v-for="g in groups" :key="g.id" class="check">
                    <input type="checkbox" :value="g.id" v-model="selectedGroupIds" />
                    <span>{{ g.name }}</span>
                </label>
            </div>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isAssignGroupsOpen = false">
                    Atšaukti
                </button>

                <button type="button" class="primary-btn fixed" :disabled="saving" @click="saveRoleGroups">
                    {{ saving ? 'Saugoma...' : 'Išsaugoti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Delete Role -->
        <ConfirmModal
            :open="isDeleteOpen"
            title="Ištrinti rolę"
            :message="`Ar tikrai norite ištrinti rolę ${deleteTarget.name} (${deleteTarget.role})?`"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            @close="isDeleteOpen = false"
            @confirm="deleteRole"
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
