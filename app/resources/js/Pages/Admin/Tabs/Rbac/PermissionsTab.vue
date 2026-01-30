<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const props = defineProps({
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

const createForm = ref({ permission: '' })
const deleteTarget = ref({ id: null, permission: '' })

const openCreate = () => {
    createForm.value = { permission: '' }
    isCreateOpen.value = true
}

const openDelete = (p) => {
    deleteTarget.value = { id: p.id, permission: p.permission }
    isDeleteOpen.value = true
}

const createPermission = async () => {
    emit('set-saving', true)

    try {
        await axios.post('/admin/rbac/permissions', createForm.value)
        isCreateOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deletePermission = async () => {
    emit('set-saving', true)

    try {
        await axios.delete(`/admin/rbac/permissions/${deleteTarget.value.id}`)
        isDeleteOpen.value = false
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div>
        <div class="header-row">
            <h3 class="title">Teisės</h3>
            <button type="button" class="primary-btn small" @click="openCreate">
                + Pridėti
            </button>
        </div>

        <table class="table" v-if="permissions.length">
            <thead>
                <tr>
                    <th>Pavadinimas</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="p in permissions" :key="p.id">
                    <td>{{ p.permission }}</td>
                    <td class="actions">
                        <button type="button" class="link-btn danger" @click="openDelete(p)">
                            Trinti
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra teisių.</div>

        <!-- Create Permission -->
        <BaseModal :open="isCreateOpen" title="Sukurti teisę" @close="isCreateOpen = false">
            <label class="field">
                <span>Pavadinimas</span>
                <input class="input" placeholder="users.manage" v-model="createForm.permission" />
            </label>

            <template #footer>
                <button type="button" class="secondary-btn" @click="isCreateOpen = false">
                    Atšaukti
                </button>

                <button type="button" class="primary-btn fixed" :disabled="saving" @click="createPermission">
                    {{ saving ? 'Kuriama...' : 'Sukurti' }}
                </button>
            </template>
        </BaseModal>

        <!-- Delete Permission -->
        <ConfirmModal
            :open="isDeleteOpen"
            title="Ištrinti teisę"
            :message="`Ar tikrai norite ištrinti teisę ${deleteTarget.permission}?`"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            @close="isDeleteOpen = false"
            @confirm="deletePermission"
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

.actions {
    width: 120px;
}

.link-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 0;
    font-weight: 600;
}

.link-btn.danger {
    color: #b42318;
}

.link-btn:hover {
    text-decoration: underline;
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
