<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import { adminRbacApi } from '@/constants/api/adminRbacApi'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'

const props = defineProps({
    roles: {
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

const emit = defineEmits(['refresh', 'set-saving', 'show-success'])

const isCreateOpen = ref(false)
const isDeleteOpen = ref(false)
const isAssignPermissionsOpen = ref(false)

const createForm = ref({ role: '', name: '' })
const deleteTarget = ref({ id: null, name: '', role: '' })
const selectedRole = ref(null)
const selectedPermissionIds = ref([])

const openCreate = () => {
    createForm.value = { role: '', name: '' }
    isCreateOpen.value = true
}

const openDelete = (r) => {
    deleteTarget.value = { id: r.id, name: r.name, role: r.role }
    isDeleteOpen.value = true
}

const openAssignPermissions = (r) => {
    selectedRole.value = r
    selectedPermissionIds.value = (r.permissions ?? []).map((p) => p.id)
    isAssignPermissionsOpen.value = true
}

const togglePermission = (id, checked) => {
    if (checked) {
        selectedPermissionIds.value = [...selectedPermissionIds.value, id]
    } else {
        selectedPermissionIds.value = selectedPermissionIds.value.filter((pid) => pid !== id)
    }
}

const createRole = async () => {
    emit('set-saving', true)

    try {
        await axios.post(adminRbacApi.roles, createForm.value)
        isCreateOpen.value = false
        emit('show-success', 'Rolė sukurta.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deleteRole = async () => {
    emit('set-saving', true)

    try {
        await axios.delete(adminRbacApi.role(deleteTarget.value.id))
        isDeleteOpen.value = false
        emit('show-success', 'Rolė pašalinta.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveRolePermissions = async () => {
    if (!selectedRole.value) return

    emit('set-saving', true)
    try {
        await axios.post(adminRbacApi.rolePermissions(selectedRole.value.id), {
            permission_ids: selectedPermissionIds.value
        })
        isAssignPermissionsOpen.value = false
        emit('show-success', 'Rolės teisės atnaujintos.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">Rolės</h3>
            <AppButton text="+ Pridėti" variant="primary" as-button class="btn-header" @click="openCreate" />
        </div>

        <table class="table" v-if="roles.length">
            <thead>
                <tr>
                    <th>Pavadinimas</th>
                    <th>Rolės kodas</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="r in roles" :key="r.id">
                    <td>{{ r.name }}</td>
                    <td>{{ r.role }}</td>
                    <td class="actions"><span class="actions-row"><button type="button" class="link-btn" @click="openAssignPermissions(r)">Teisės</button><button type="button" class="link-btn danger" @click="openDelete(r)">Trinti</button></span></td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra rolių.</div>

        <BaseModal :open="isCreateOpen" title="Sukurti rolę" @close="isCreateOpen = false">
            <AppInput v-model="createForm.role" label="Rolės kodas" placeholder="ROLE_MODERATOR" />
            <AppInput v-model="createForm.name" label="Pavadinimas" placeholder="Moderatorius" />

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isCreateOpen = false" />
                <AppButton
                    :text="saving ? 'Kuriama...' : 'Sukurti'"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="createRole"
                />
            </template>
        </BaseModal>

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

        <BaseModal
            :open="isAssignPermissionsOpen"
            title="Priskirti teises rolei"
            width="720px"
            @close="isAssignPermissionsOpen = false"
        >
            <div class="hint">
                Rolė: <strong>{{ selectedRole?.name }}</strong>
            </div>

            <div class="checklist">
                <label v-for="p in permissions" :key="p.id" class="check">
                    <AppCheckbox
                        :model-value="selectedPermissionIds.includes(p.id)"
                        @update:model-value="(checked) => togglePermission(p.id, checked)"
                    />
                    <span>{{ p.name || p.permission }}</span>
                </label>
            </div>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isAssignPermissionsOpen = false" />
                <AppButton
                    :text="saving ? 'Saugoma...' : 'Išsaugoti'"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="saveRolePermissions"
                />
            </template>
        </BaseModal>
    </div>
</template>
