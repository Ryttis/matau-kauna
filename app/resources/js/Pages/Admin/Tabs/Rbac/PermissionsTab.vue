<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'

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

const emit = defineEmits(['refresh', 'set-saving', 'show-success'])

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
        emit('show-success', 'Teisė sukurta.')
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
        emit('show-success', 'Teisė pašalinta.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">Teisės</h3>
            <AppButton text="+ Pridėti" variant="primary" as-button class="btn-header" @click="openCreate" />
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
            <AppInput v-model="createForm.permission" label="Pavadinimas" placeholder="users.manage" />

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isCreateOpen = false" />
                <AppButton
                    :text="saving ? 'Kuriama...' : 'Sukurti'"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="createPermission"
                />
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
.actions {
    width: 120px;
}
</style>
