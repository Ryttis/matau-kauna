<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const props = defineProps({
    rules: Array,
    categories: Array,
    institutions: Array,
})

const showFormModal = ref(false)
const showDeleteModal = ref(false)
const formRuleId = ref(null)
const ruleToDelete = ref(null)
const ruleFormSubmitting = ref(false)
const deleteSubmitting = ref(false)

const defaultInstitutionId = computed(() =>
    props.institutions?.length ? String(props.institutions[0].id) : ''
)

const categoryOptions = computed(() => {
    const options = [{ value: '', label: 'Visos kategorijos' }]
    ;(props.categories || []).filter(Boolean).forEach((c) => options.push({ value: c, label: c }))
    return options
})

function getDefaultForm(rule = null) {
    const defaultId = defaultInstitutionId.value
    return {
        name: rule?.name ?? '',
        category: rule?.category ?? '',
        institution_id: rule?.institution_id ? String(rule.institution_id) : defaultId,
        is_active: rule?.is_active ?? true,
    }
}

const form = ref(getDefaultForm())

function openCreate() {
    formRuleId.value = null
    form.value = getDefaultForm()
    showFormModal.value = true
}

function openEdit(rule) {
    formRuleId.value = rule.id
    form.value = getDefaultForm(rule)
    showFormModal.value = true
}

function buildPayload() {
    return {
        name: form.value.name || null,
        category: form.value.category || null,
        institution_id: Number(form.value.institution_id),
        is_active: form.value.is_active,
    }
}

function tryCloseAssignmentRuleModal() {
    if (ruleFormSubmitting.value) {
        return
    }
    showFormModal.value = false
}

function submitForm() {
    if (ruleFormSubmitting.value) {
        return
    }
    const payload = buildPayload()
    ruleFormSubmitting.value = true
    const options = {
        preserveScroll: true,
        onSuccess: () => { showFormModal.value = false },
        onFinish: () => { ruleFormSubmitting.value = false },
    }
    if (formRuleId.value) {
        router.put(route('automation.update', formRuleId.value), payload, options)
    } else {
        router.post(route('automation.store'), payload, options)
    }
}

function confirmDelete(rule) {
    ruleToDelete.value = rule
    showDeleteModal.value = true
}

function doDelete() {
    if (!ruleToDelete.value || deleteSubmitting.value) {
        return
    }
    deleteSubmitting.value = true
    router.delete(route('automation.destroy', ruleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            ruleToDelete.value = null
        },
        onFinish: () => { deleteSubmitting.value = false },
    })
}
</script>

<template>
    <div>
        <p class="automation-desc">
            Problemos pagal kategoriją automatiškai priskiriamos įstaigoms.
        </p>
        <div class="automation-toolbar">
            <AppButton text="Pridėti taisyklę" variant="primary" as-button @click="openCreate" />
        </div>
        <div class="automation-table-wrap">
            <table v-if="rules?.length" class="automation-table">
                <thead>
                    <tr>
                        <th>Pavadinimas</th>
                        <th>Kategorija</th>
                        <th>Įstaiga</th>
                        <th>Aktyvi</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rules" :key="r.id">
                        <td>{{ r.name || '-' }}</td>
                        <td>{{ r.category || '-' }}</td>
                        <td>{{ r.institution_name || '-' }}</td>
                        <td>{{ r.is_active ? 'Taip' : 'Ne' }}</td>
                        <td>
                            <button type="button" class="automation-link" @click="openEdit(r)">Redaguoti</button>
                            <span class="automation-sep">|</span>
                            <button type="button" class="automation-link automation-link--danger" @click="confirmDelete(r)">Trinti</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="automation-empty">Nėra taisyklių. Pridėkite taisyklę, kad problemos būtų automatiškai priskiriamos įstaigoms.</p>
        </div>

        <BaseModal
            :show="showFormModal"
            title="Taisyklė"
            :max-width="'520px'"
            class="automation-rule-modal"
            footer-layout="column"
            @close="tryCloseAssignmentRuleModal"
        >
            <div class="automation-form">
                <AppInput v-model="form.name" label="Pavadinimas (nebūtina)" placeholder="pvz. Duobės" />
                <AppSelect v-model="form.category" label="Kategorija" :placeholder="''">
                    <option v-for="opt in categoryOptions" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <AppSelect v-model="form.institution_id" label="Įstaiga" placeholder="Pasirinkite įstaigą">
                    <option v-for="i in institutions" :key="i.id" :value="String(i.id)">{{ i.name }}</option>
                </AppSelect>
                <label class="automation-form-checkbox">
                    <AppCheckbox v-model="form.is_active" />
                    <span>Taisyklė aktyvi</span>
                </label>
            </div>
            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button :disabled="ruleFormSubmitting" @click="tryCloseAssignmentRuleModal" />
                <AppButton text="Išsaugoti" variant="primary" as-button :loading="ruleFormSubmitting" @click="submitForm" />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showDeleteModal"
            message="Ar tikrai norite pašalinti šią taisyklę?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            :loading="deleteSubmitting"
            @close="showDeleteModal = false"
            @confirm="doDelete"
        />
    </div>
</template>
