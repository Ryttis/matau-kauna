<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import { adminRbacApi } from '@/constants/api/adminRbacApi'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'

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

const emit = defineEmits(['refresh', 'set-saving', 'show-success'])

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

const togglePermission = (id, checked) => {
    if (checked) {
        selectedPermissionIds.value = [...selectedPermissionIds.value, id]
    } else {
        selectedPermissionIds.value = selectedPermissionIds.value.filter((i) => i !== id)
    }
}

const createGroup = async () => {
    emit('set-saving', true)

    try {
        await axios.post(adminRbacApi.groups, createForm.value)
        isCreateOpen.value = false
        emit('show-success', 'Grupė sukurta.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deleteGroup = async () => {
    emit('set-saving', true)

    try {
        await axios.delete(adminRbacApi.group(deleteTarget.value.id))
        isDeleteOpen.value = false
        emit('show-success', 'Grupė pašalinta.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveGroupPermissions = async () => {
    if (!selectedGroup.value) return

    emit('set-saving', true)

    try {
        await axios.post(adminRbacApi.groupPermissions(selectedGroup.value.id), {
            permission_ids: selectedPermissionIds.value
        })

        isAssignPermissionsOpen.value = false
        emit('show-success', 'Grupės teisės atnaujintos.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">Grupės</h3>
            <AppButton text="+ Pridėti" variant="primary" as-button class="btn-header" @click="openCreate" />
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
                    <td class="actions"><span class="actions-row"><button type="button" class="link-btn" @click="openAssignPermissions(g)">Teisės</button><button type="button" class="link-btn danger" @click="openDelete(g)">Trinti</button></span></td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra grupių.</div>

        <BaseModal :open="isCreateOpen" title="Sukurti grupę" @close="isCreateOpen = false">
            <AppInput v-model="createForm.name" placeholder="Pavadinimas (pvz. Users)" />

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isCreateOpen = false" />
                <AppButton
                    :text="saving ? 'Kuriama...' : 'Sukurti'"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="createGroup"
                />
            </template>
        </BaseModal>

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
                    <AppCheckbox
                        :model-value="selectedPermissionIds.includes(p.id)"
                        @update:model-value="(checked) => togglePermission(p.id, checked)"
                    />
                    <span>{{ p.permission }}</span>
                </label>
            </div>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isAssignPermissionsOpen = false" />
                <AppButton
                    :text="saving ? 'Saugoma...' : 'Išsaugoti'"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="saveGroupPermissions"
                />
            </template>
        </BaseModal>

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
