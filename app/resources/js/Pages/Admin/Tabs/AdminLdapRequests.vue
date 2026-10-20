<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import { adminApi } from '@/constants/api/adminApi'
import { adminRbacApi } from '@/constants/api/adminRbacApi'

const base = '/admin/ldap-access-requests'
const page = ref(null)
const status = ref('pending')
const roles = ref([])
const institutions = ref([])
const selected = ref(null)
const mode = ref('create')
const users = ref([])
const search = ref('')
const busy = ref(false)
const error = ref('')
const message = ref('')
const form = ref({})
const labels = { pending: 'Laukia patvirtinimo', approved: 'Patvirtintas', rejected: 'Atmestas' }
const date = value => value ? new Date(value).toLocaleString('lt-LT') : '—'
const showError = e => { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Nepavyko atlikti veiksmo.' }
const load = async (number = 1) => {
    try {
        page.value = (await axios.get(base, { params: { status: status.value, page: number } })).data
    } catch (e) { showError(e) }
}
const choose = request => {
    selected.value = request
    mode.value = 'create'
    users.value = []
    search.value = ''
    error.value = ''
    message.value = ''
    form.value = { name: request.directory_name || '', surname: '', email: request.directory_email || '', institution_id: null, role_ids: [], user_id: null }
}
const findUsers = async () => {
    try { users.value = (await axios.get(adminRbacApi.users, { params: { search: search.value, per_page: 100 } })).data.data }
    catch (e) { showError(e) }
}
const act = async (request, action) => {
    busy.value = true
    error.value = ''
    message.value = ''
    try {
        const data = { institution_id: form.value.institution_id || null, role_ids: form.value.role_ids }
        if (mode.value === 'link') data.user_id = form.value.user_id
        else Object.assign(data, { name: form.value.name, surname: form.value.surname, email: form.value.email })
        await axios.post(`${base}/${request.id}/${action}`, action === 'approve' ? data : {})
        selected.value = null
        message.value = action === 'approve' ? 'LDAP paskyra patvirtinta.' : 'Prašymas atmestas.'
        await load(page.value?.current_page || 1)
    } catch (e) { showError(e) }
    finally { busy.value = false }
}
onMounted(async () => {
    await load()
    try {
        const [r, i] = await Promise.all([axios.get(adminRbacApi.roles), axios.get(adminApi.institutionsList)])
        roles.value = r.data.filter(role => role.role !== 'ROLE_REPORTER')
        institutions.value = i.data
    } catch (e) { showError(e) }
})
</script>

<template>
    <section class="ldap-requests">
        <h2>Laukiantys LDAP vartotojai</h2>
        <p>LDAP patvirtina tapatybę. Administratorius suteikia prieigą, parinkdamas darbuotojo roles ir įstaigą.</p>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
        <p v-if="message" role="status">{{ message }}</p>
        <label>Būsena
            <select v-model="status" :disabled="busy" @change="load()">
                <option value="">Visos</option>
                <option v-for="(label, key) in labels" :key="key" :value="key">{{ label }}</option>
            </select>
        </label>
        <table>
            <thead><tr><th>LDAP vardas</th><th>Katalogas</th><th>Vardas / el. paštas</th><th>Pirmas bandymas</th><th>Paskutinis bandymas</th><th>Būsena</th><th>Veiksmai</th></tr></thead>
            <tbody>
                <tr v-for="request in page?.data || []" :key="request.id">
                    <td>{{ request.ldap_username }}</td><td>{{ request.ldap_source }}</td>
                    <td>{{ request.directory_name || '—' }}<br>{{ request.directory_email || '—' }}</td>
                    <td>{{ date(request.first_seen_at) }}</td><td>{{ date(request.last_seen_at) }}</td><td>{{ labels[request.status] }}</td>
                    <td v-if="request.status !== 'approved'" class="actions">
                        <AppButton text="Patvirtinti" as-button :disabled="busy" @click="choose(request)" />
                        <AppButton v-if="request.status === 'pending'" text="Atmesti" variant="danger" as-button :disabled="busy" @click="act(request, 'reject')" />
                    </td><td v-else>Naudotojas #{{ request.linked_user_id }}</td>
                </tr>
                <tr v-if="page && !page.data.length"><td colspan="7">Prašymų nėra.</td></tr>
            </tbody>
        </table>
        <div v-if="page && page.last_page > 1" class="actions">
            <AppButton text="Ankstesnis" as-button :disabled="busy || page.current_page <= 1" @click="load(page.current_page - 1)" />
            <span>{{ page.current_page }} / {{ page.last_page }}</span>
            <AppButton text="Kitas" as-button :disabled="busy || page.current_page >= page.last_page" @click="load(page.current_page + 1)" />
        </div>
        <form v-if="selected" @submit.prevent="act(selected, 'approve')">
            <h3>Patvirtinti: {{ selected.ldap_username }} ({{ selected.ldap_source }})</h3>
            <fieldset :disabled="busy">
                <label>Naudotojas<select v-model="mode"><option value="create">Sukurti naują</option><option value="link">Susieti esamą</option></select></label>
                <template v-if="mode === 'create'">
                    <label>Vardas<input v-model="form.name" required maxlength="255"></label>
                    <label>Pavardė<input v-model="form.surname" maxlength="255"></label>
                    <label>El. paštas<input v-model="form.email" required type="email" maxlength="255"></label>
                </template>
                <template v-else>
                    <p>Pasirinkite naudotoją aiškiai. Jo rolės ir įstaiga bus pakeistos pagal šią formą.</p>
                    <label>Paieška pagal vardą ar el. paštą<input v-model="search"></label>
                    <AppButton text="Ieškoti" as-button @click="findUsers" />
                    <label>Esamas naudotojas<select v-model="form.user_id" required><option :value="null" disabled>Pasirinkite</option><option v-for="user in users" :key="user.id" :value="user.id">#{{ user.id }} {{ user.name }} {{ user.surname }} — {{ user.email }}</option></select></label>
                    <p>Rodoma iki 100 rezultatų. Patikslinkite paiešką, jei naudotojo nematote.</p>
                </template>
                <label>Įstaiga<select v-model="form.institution_id"><option :value="null">Be įstaigos</option><option v-for="institution in institutions" :key="institution.id" :value="institution.id">{{ institution.name }}</option></select></label>
                <p>Darbuotojo ir įstaigos administratoriaus rolėms įstaiga privaloma.</p>
                <fieldset><legend>Darbuotojo rolės (bent viena)</legend><label v-for="role in roles" :key="role.id" class="role"><input v-model="form.role_ids" type="checkbox" :value="role.id">{{ role.name }}</label></fieldset>
                <div class="actions"><AppButton text="Patvirtinti ir suteikti prieigą" :loading="busy" :disabled="!form.role_ids.length" /><AppButton text="Atšaukti" variant="secondary" as-button @click="selected = null" /></div>
            </fieldset>
        </form>
    </section>
</template>

<style scoped>
.ldap-requests { display: grid; gap: 16px; }
h2, h3 { font-weight: 600; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
label { display: grid; gap: 6px; margin: 10px 0; }
input, select { border: 1px solid #aaa; border-radius: 4px; padding: 8px; }
form { max-width: 720px; border: 1px solid #ddd; padding: 20px; }
.actions { display: flex; gap: 10px; align-items: center; }
.role { display: flex; align-items: center; }
.error { color: #b91c1c; }
</style>
