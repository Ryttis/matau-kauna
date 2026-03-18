<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'

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

const emit = defineEmits(['refresh', 'set-saving', 'show-success'])

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
        emit('show-success', 'Rolė sukurta.')
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
        emit('show-success', 'Rolė pašalinta.')
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
        emit('show-success', 'Rolės grupės atnaujintos.')
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
                    <td class="actions"><span class="actions-row"><button type="button" class="link-btn" @click="openAssignGroups(r)">Grupės</button><button type="button" class="link-btn danger" @click="openDelete(r)">Trinti</button></span></td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra rolių.</div>

        <!-- Create Role -->
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
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isAssignGroupsOpen = false" />
                <AppButton
                    :text="saving ? 'Saugoma...' : 'Išsaugoti'"
                    variant="primary"
                    as-button
                    :disabled="saving"
                    @click="saveRoleGroups"
                />
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
