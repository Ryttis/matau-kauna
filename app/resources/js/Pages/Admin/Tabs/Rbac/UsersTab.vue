<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import { adminRbacApi } from '@/constants/api/adminRbacApi'
import { invitationsApi } from '@/constants/api/invitationsApi'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import { ROLE_INSTITUTION_ADMIN, ROLE_EMPLOYEE } from '@/constants/domain/roles'

const props = defineProps({
    usersPage: Object,
    roles: Array,
    institutions: { type: Array, default: () => [] },
    saving: Boolean
})

const emit = defineEmits(['refresh', 'set-saving', 'show-success'])

const isCreateOpen = ref(false)
const isEditOpen = ref(false)
const isDeleteOpen = ref(false)
const isAssignRolesOpen = ref(false)
const isInviteOpen = ref(false)
const inviteError = ref('')
const assignRolesError = ref('')
const createError = ref('')
const createErrors = ref([])

const createForm = ref({ name: '', email: '', password: '', password_confirmation: '', institution_id: '', role_ids: [] })
const inviteForm = ref({ email: '', institution_id: '', role_id: '' })
const editForm = ref({ id: null, name: '', email: '', password: '', institution_id: '' })
const deleteTarget = ref({ id: null, name: '', email: '' })

const selectedUser = ref(null)
const selectedRoleIds = ref([])

const openCreate = () => {
    createForm.value = { name: '', email: '', password: '', password_confirmation: '', institution_id: '', role_ids: [] }
    createError.value = ''
    createErrors.value = []
    isCreateOpen.value = true
}

const openEdit = (u) => {
    editForm.value = { id: u.id, name: u.name, email: u.email, password: '', password_confirmation: '', institution_id: u.institution_id ?? '' }
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

const selectedInviteInstitution = computed(() =>
    (props.institutions ?? []).find(
        (inst) => String(inst.id) === String(inviteForm.value.institution_id)
    )
)

const isInviteInstitutionEmailOnly = computed(() => !!selectedInviteInstitution.value?.is_email_only)

const assignableRoles = computed(() => props.roles ?? [])

const openInvite = () => {
    inviteForm.value = { email: '', institution_id: '', role_id: '' }
    inviteError.value = ''
    isInviteOpen.value = true
}

const inviteUser = async () => {
    inviteError.value = ''
    if (isInviteInstitutionEmailOnly.value) {
        inviteError.value = 'Ši įstaiga pažymėta kaip el. pašto integracija, todėl pakvietimai negalimi.';
        return
    }
    emit('set-saving', true)
    try {
        await axios.post(invitationsApi.store, inviteForm.value)
        isInviteOpen.value = false
        emit('show-success', 'Pakvietimas išsiųstas.')
        emit('refresh')
    } catch (e) {
        inviteError.value = e.response?.data?.message ?? 'Nepavyko išsiųsti pakvietimo.'
    } finally {
        emit('set-saving', false)
    }
}

const createUser = async () => {
    createError.value = ''
    emit('set-saving', true)
    try {
        await axios.post(adminRbacApi.users, {
            name: createForm.value.name,
            email: createForm.value.email,
            password: createForm.value.password,
            password_confirmation: createForm.value.password_confirmation,
            institution_id: createForm.value.institution_id || null,
            role_ids: createForm.value.role_ids.length ? createForm.value.role_ids : null
        })
        isCreateOpen.value = false
        emit('show-success', 'Naudotojas sukurtas.')
        emit('refresh')
    } catch (e) {
        const errors = e.response?.data?.errors
        if (errors && typeof errors === 'object') {
            const list = [...new Set(Object.values(errors).flat().filter(Boolean))]
            createErrors.value = list.length ? list : [e.response?.data?.message || 'Nepavyko sukurti naudotojo.']
            createError.value = createErrors.value.length === 1 ? createErrors.value[0] : ''
        } else {
            createError.value = e.response?.data?.message || 'Nepavyko sukurti naudotojo.'
            createErrors.value = createError.value ? [createError.value] : []
        }
    } finally {
        emit('set-saving', false)
    }
}

const updateUser = async () => {
    emit('set-saving', true)
    try {
        const payload = {
            name: editForm.value.name,
            email: editForm.value.email,
            institution_id: editForm.value.institution_id || null
        }
        if (editForm.value.password) {
            payload.password = editForm.value.password
            payload.password_confirmation = editForm.value.password_confirmation
        }
        await axios.put(adminRbacApi.user(editForm.value.id), payload)
        isEditOpen.value = false
        emit('show-success', 'Naudotojas atnaujintas.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const deleteUser = async () => {
    emit('set-saving', true)
    try {
        await axios.delete(adminRbacApi.user(deleteTarget.value.id))
        isDeleteOpen.value = false
        emit('show-success', 'Naudotojas pašalintas.')
        emit('refresh')
    } finally {
        emit('set-saving', false)
    }
}

const saveUserRoles = async () => {
    if (!selectedUser.value) return

    assignRolesError.value = ''

    emit('set-saving', true)
    try {
        await axios.post(adminRbacApi.userRoles(selectedUser.value.id), {
            role_ids: selectedRoleIds.value
        })
        isAssignRolesOpen.value = false
        emit('show-success', 'Naudotojo rolės atnaujintos.')
        emit('refresh')
    } catch (e) {
        assignRolesError.value = e.response?.data?.message ?? 'Nepavyko išsaugoti rolių.'
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">Naudotojai</h3>
            <div class="header-actions">
                <AppButton text="Pakviesti" variant="secondary" as-button class="btn-header" @click="openInvite" />
                <AppButton text="+ Pridėti" variant="primary" as-button class="btn-header" @click="openCreate" />
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
                    <td class="actions"><span class="actions-row"><button type="button" class="link-btn" @click="openAssignRoles(u)">Rolės</button><button type="button" class="link-btn" @click="openEdit(u)">Redaguoti</button><button type="button" class="link-btn danger" @click="openDelete(u)">Trinti</button></span></td>
                </tr>
            </tbody>
        </table>

        <div v-else>Nėra naudotojų.</div>

        <BaseModal :open="isCreateOpen" title="Sukurti naudotoją" @close="isCreateOpen = false">
            <div v-if="createErrors.length" class="error">
                <ul v-if="createErrors.length > 1" class="error-list">
                    <li v-for="(err, i) in createErrors" :key="i">{{ err }}</li>
                </ul>
                <span v-else>{{ createErrors[0] }}</span>
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
                <input class="input" type="password" v-model="createForm.password" placeholder="Min. 8 simb., didž./maž. raidė, specialus" />
            </label>
            <label class="field">
                <span>Pakartokite slaptažodį</span>
                <input class="input" type="password" v-model="createForm.password_confirmation" />
            </label>

            <label class="field">
                <span>Įstaiga</span>
                <select class="input" v-model="createForm.institution_id">
                    <option value="">— Nepriskirta</option>
                    <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
            </label>

            <div class="field">
                <span>Rolės</span>
                <div class="checklist">
                    <label v-for="r in assignableRoles" :key="r.id" class="check">
                        <input type="checkbox" :value="r.id" v-model="createForm.role_ids" />
                        <span>{{ r.name }}</span>
                    </label>
                </div>
            </div>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isCreateOpen = false" />
                <AppButton :text="saving ? 'Kuriama...' : 'Sukurti'" variant="primary" as-button :disabled="saving" @click="createUser" />
            </template>
        </BaseModal>

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
                    <option v-for="i in institutions" :key="i.id" :value="i.id" :disabled="i.is_email_only">
                        {{ i.name }}{{ i.is_email_only ? ' (tik el. paštu)' : '' }}
                    </option>
                </select>
            </label>
            <div v-if="isInviteInstitutionEmailOnly" class="hint">
                Ši įstaiga yra tik el. pašto integracija, todėl pakvietimai nesiunčiami.
            </div>
            <label class="field">
                <span>Rolė</span>
                <select class="input" v-model="inviteForm.role_id">
                    <option value="">— Pasirinkite</option>
                    <option v-for="r in inviteRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
            </label>
            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isInviteOpen = false" />
                <AppButton
                    :text="saving ? 'Siunčiama...' : 'Siųsti pakvietimą'"
                    variant="primary"
                    as-button
                    :disabled="saving || !inviteForm.email || !inviteForm.institution_id || !inviteForm.role_id || isInviteInstitutionEmailOnly"
                    @click="inviteUser"
                />
            </template>
        </BaseModal>

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
                <input class="input" type="password" v-model="editForm.password" placeholder="Palikite tuščią, jei nekeičiate" />
            </label>
            <label class="field" v-if="editForm.password">
                <span>Pakartokite slaptažodį</span>
                <input class="input" type="password" v-model="editForm.password_confirmation" />
            </label>

            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isEditOpen = false" />
                <AppButton text="Išsaugoti" variant="primary" as-button :disabled="saving" @click="updateUser" />
            </template>
        </BaseModal>

        <BaseModal
            :open="isAssignRolesOpen"
            title="Priskirti roles"
            width="640px"
            @close="isAssignRolesOpen = false"
        >
            <div v-if="assignRolesError" class="error">{{ assignRolesError }}</div>
            <div class="hint">
                Naudotojas: <strong>{{ selectedUser?.name }}</strong>
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
                <AppButton text="Atšaukti" variant="secondary" as-button @click="isAssignRolesOpen = false" />
                <AppButton text="Išsaugoti" variant="primary" as-button :disabled="saving" @click="saveUserRoles" />
            </template>
        </BaseModal>

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
