<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import { adminRbacApi } from '@/constants/api/adminRbacApi'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'

defineProps({
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

const createForm = ref({ name: '', permission: '' })
const deleteTarget = ref({ id: null, name: '', permission: '' })

const openCreate = () => {
    createForm.value = { name: '', permission: '' }
    isCreateOpen.value = true
}

const openDelete = (p) => {
    deleteTarget.value = { id: p.id, name: p.name, permission: p.permission }
    isDeleteOpen.value = true
}

const createPermission = async () => {
    emit('set-saving', true)

    try {
        await axios.post(adminRbacApi.permissions, createForm.value)
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
        await axios.delete(adminRbacApi.permission(deleteTarget.value.id))
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
                    <th>Teisė</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="p in permissions" :key="p.id">
                    <td>{{ p.name || p.permission }}</td>
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

        <BaseModal :open="isCreateOpen" title="Sukurti teisę" @close="isCreateOpen = false">
            <AppInput v-model="createForm.name" label="Pavadinimas" placeholder="Administratoriaus leidimas" />
            <AppInput v-model="createForm.permission" label="Teisė" placeholder="admin.access" />

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

        <ConfirmModal
            :open="isDeleteOpen"
            title="Ištrinti teisę"
            :message="`Ar tikrai norite ištrinti teisę ${deleteTarget.name || deleteTarget.permission} (${deleteTarget.permission})?`"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            :loading="saving"
            @close="isDeleteOpen = false"
            @confirm="deletePermission"
        />
    </div>
</template>
