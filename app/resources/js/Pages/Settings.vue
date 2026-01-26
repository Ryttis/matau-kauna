<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    employees: Array,
})

const page = usePage()
const user = page.props.auth.user

const profileForm = useForm({
    name: user.name || '',
    surname: user.surname || '',
    email: user.email || '',
})

const employeeForm = useForm({
    name: '',
    surname: '',
    email: '',
})

const institutionForm = useForm({
    name: user.institution?.name || '',
    code: user.institution?.code || '',
})

const privacyForm = useForm({
    two_factor_enabled: !!user.two_factor_enabled,
    email_notifications_enabled: !!user.email_notifications_enabled,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const deleteAccountForm = useForm({
    password: '',
})

const showDeleteModal = ref(false)

const sections = ref({
    profile: true,
    employee: true,
    institution: true,
    privacy: true,
})

const toggleSection = (section) => {
    sections.value[section] = !sections.value[section]
}

const updateProfile = () => {
    profileForm.patch(route('settings.profile'), {
        preserveScroll: true,
    })
}

const storeEmployee = () => {
    employeeForm.post(route('settings.employees'), {
        preserveScroll: true,
        onSuccess: () => employeeForm.reset(),
    })
}

const updateInstitution = () => {
    institutionForm.patch(route('settings.institution'), {
        preserveScroll: true,
    })
}

const updatePrivacy = () => {
    privacyForm.patch(route('settings.privacy'), {
        preserveScroll: true,
    })
}

const updatePassword = () => {
    passwordForm.put(route('settings.password'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

const deleteAccount = () => {
    deleteAccountForm.delete(route('settings.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            deleteAccountForm.reset()
        },
    })
}
</script>

<template>
    <Head title="Nustatymai" />

    <AuthenticatedLayout>
        <div class="settings-container">
            <!-- Profilio duomenys -->
            <div class="section">
                <div class="section-header" @click="toggleSection('profile')">
                    <h2>Profilio duomenys</h2>
                    <i class="fa-solid" :class="sections.profile ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </div>
                
                <div v-if="sections.profile" class="section-content">
                    <form @submit.prevent="updateProfile" class="form-grid">
                        <div class="input-group">
                            <input v-model="profileForm.name" type="text" placeholder="Vardas" />
                            <i class="fa-solid fa-pencil edit-icon"></i>
                        </div>
                        <div class="input-group">
                            <input v-model="profileForm.surname" type="text" placeholder="Pavardė" />
                            <i class="fa-solid fa-pencil edit-icon"></i>
                        </div>
                        <div class="input-group">
                            <input v-model="profileForm.email" type="email" placeholder="El. paštas" />
                            <i class="fa-solid fa-pencil edit-icon"></i>
                        </div>
                        <button type="submit" class="btn-save" :disabled="profileForm.processing">Saugoti</button>
                    </form>
                </div>
            </div>

            <!-- Darbuotojas -->
            <div class="section">
                <div class="section-header" @click="toggleSection('employee')">
                    <h2>Darbuotojas</h2>
                    <i class="fa-solid fa-plus add-icon" @click.stop=""></i>
                </div>
                
                <div v-if="sections.employee" class="section-content">
                    <form @submit.prevent="storeEmployee" class="form-grid">
                        <div class="input-group no-icon">
                            <input v-model="employeeForm.name" type="text" placeholder="Vardas" />
                        </div>
                        <div class="input-group no-icon">
                            <input v-model="employeeForm.surname" type="text" placeholder="Pavardė" />
                        </div>
                        <div class="input-group no-icon">
                            <input v-model="employeeForm.email" type="email" placeholder="El. paštas" />
                        </div>
                        <button type="submit" class="btn-save" :disabled="employeeForm.processing">Saugoti</button>
                    </form>

                    <div v-if="employees && employees.length > 0" class="employee-list">
                        <h3>Darbuotojų sąrašas:</h3>
                        <ul>
                            <li v-for="emp in employees" :key="emp.id">
                                {{ emp.name }} {{ emp.surname }} ({{ emp.email }})
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Įstaigos nustatymai -->
            <div class="section">
                <div class="section-header" @click="toggleSection('institution')">
                    <h2>Įstaigos nustatymai</h2>
                    <i class="fa-solid" :class="sections.institution ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </div>
                
                <div v-if="sections.institution" class="section-content">
                    <form @submit.prevent="updateInstitution" class="form-grid">
                        <div class="input-group">
                            <input v-model="institutionForm.name" type="text" placeholder="Pavadinimas" />
                            <i class="fa-solid fa-pencil edit-icon"></i>
                        </div>
                        <div class="input-group">
                            <input v-model="institutionForm.code" type="text" placeholder="Kodas" />
                            <i class="fa-solid fa-pencil edit-icon"></i>
                        </div>
                        <button type="submit" class="btn-save" :disabled="institutionForm.processing">Saugoti</button>
                    </form>
                </div>
            </div>

            <!-- Privatumo taisyklės -->
            <div class="section no-border">
                <div class="section-header" @click="toggleSection('privacy')">
                    <h2>Privatumo taisyklės</h2>
                    <i class="fa-solid" :class="sections.privacy ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </div>
                
                <div v-if="sections.privacy" class="section-content">
                    <div class="toggle-group">
                        <span>2 FA autentifikavimas</span>
                        <label class="switch">
                            <input v-model="privacyForm.two_factor_enabled" type="checkbox" @change="updatePrivacy">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <div class="toggle-group">
                        <span>Informacijos atnaujinimo siuntimas el. paštu</span>
                        <label class="switch">
                            <input v-model="privacyForm.email_notifications_enabled" type="checkbox" @change="updatePrivacy">
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Keisti slaptažodį -->
            <div class="section simple">
                <div class="section-header">
                    <h2 @click="updatePassword" style="cursor: pointer">Keisti slaptažodį</h2>
                </div>
                <div class="section-content">
                    <form @submit.prevent="updatePassword" class="password-form">
                        <div class="input-group no-icon" v-if="passwordForm.password || passwordForm.current_password">
                             <input v-model="passwordForm.current_password" type="password" placeholder="Dabartinis slaptažodis" />
                             <input v-model="passwordForm.password" type="password" placeholder="Naujas slaptažodis" />
                             <input v-model="passwordForm.password_confirmation" type="password" placeholder="Pakartoti naują" />
                             <button type="submit" class="btn-save" :disabled="passwordForm.processing">Keisti slaptažodį</button>
                        </div>
                        <div v-else class="placeholder-text" @click="passwordForm.current_password = ' '">
                             <!-- This is just to show fields when clicking, simplified for UI -->
                        </div>
                    </form>
                </div>
            </div>

            <!-- Šalinti paskyrą -->
            <div class="section simple delete-section">
                <div class="section-header">
                    <h2 @click="showDeleteModal = true" style="cursor: pointer">Šalinti paskyrą</h2>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="modal-overlay">
            <div class="modal">
                <h3>Ar tikrai norite pašalinti paskyrą?</h3>
                <p>Šio veiksmo atšaukti negalėsite.</p>
                
                <div class="modal-inputs">
                    <input 
                        v-model="deleteAccountForm.password" 
                        type="password" 
                        placeholder="Įveskite slaptažodį patvirtinimui"
                        class="modal-input"
                    />
                    <div v-if="deleteAccountForm.errors.password" class="error">
                        {{ deleteAccountForm.errors.password }}
                    </div>
                </div>

                <div class="modal-actions">
                    <button @click="showDeleteModal = false" class="btn-cancel">Atšaukti</button>
                    <button @click="deleteAccount" class="btn-delete" :disabled="deleteAccountForm.processing">Šalinti</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.settings-container {
    max-width: 800px;
    background: #fff;
    padding: 20px;
}

.section {
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    margin-bottom: 15px;
}

.section-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #000;
}

.add-icon {
    color: #000;
    font-size: 16px;
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 400px;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-group input {
    width: 100%;
    padding: 10px 40px 10px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    font-size: 14px;
    background-color: #F9FAFB;
}

.input-group.no-icon input {
    padding-right: 12px;
}

.edit-icon {
    position: absolute;
    right: 12px;
    color: #9CA3AF;
    font-size: 14px;
}

.btn-save {
    background-color: #1946A0;
    color: white;
    padding: 10px 24px;
    border-radius: 6px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    width: fit-content;
    margin-top: 5px;
}

.btn-save:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.employee-list {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #E5E7EB;
}

.employee-list h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.employee-list ul {
    list-style: none;
    padding: 0;
}

.employee-list li {
    font-size: 14px;
    color: #4B5563;
    padding: 4px 0;
}

.toggle-group {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 400px;
    margin-bottom: 10px;
}

.toggle-group span {
    font-size: 15px;
    font-weight: 500;
}

/* Switch styling */
.switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 22px;
}

.switch input { 
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}

input:checked + .slider {
  background-color: #1946A0;
}

input:focus + .slider {
  box-shadow: 0 0 1px #1946A0;
}

input:checked + .slider:before {
  -webkit-transform: translateX(22px);
  -ms-transform: translateX(22px);
  transform: translateX(22px);
}

.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

.password-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 400px;
}

.delete-section h2 {
    color: #000;
}

/* Modal styling */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 450px;
    width: 100%;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.modal h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.modal p {
    color: #6B7280;
    margin-bottom: 20px;
}

.modal-inputs {
    margin-bottom: 20px;
}

.modal-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-cancel {
    padding: 8px 16px;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    background: white;
    cursor: pointer;
}

.btn-delete {
    padding: 8px 16px;
    background: #DC2626;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.error {
    color: #DC2626;
    font-size: 12px;
    margin-top: 4px;
}
</style>
