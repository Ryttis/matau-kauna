<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const props = defineProps({
    resolutionTermRules: Array,
    institutions: Array,
    classifierTypesWithNatures: Array,
})

const showTermRuleModal = ref(false)
const showTermRuleDeleteModal = ref(false)
const termRuleFormId = ref(null)
const termRuleToDelete = ref(null)
const termRuleFormSubmitting = ref(false)
const deleteSubmitting = ref(false)

const termRuleForm = ref({
    days: 14,
    institution_id: '',
    category: '',
    problem_type_id: '',
    problem_nature_id: '',
    is_active: true,
})

function getDefaultTermRuleForm(rule = null) {
    return {
        days: rule?.days ?? 14,
        institution_id: rule?.institution_id ? String(rule.institution_id) : '',
        category: rule?.category ?? rule?.problem_type_name ?? '',
        problem_type_id: rule?.problem_type_id ? String(rule.problem_type_id) : '',
        problem_nature_id: rule?.problem_nature_id ? String(rule.problem_nature_id) : '',
        is_active: rule?.is_active ?? true,
    }
}

function openTermRuleCreate() {
    termRuleFormId.value = null
    termRuleForm.value = getDefaultTermRuleForm()
    showTermRuleModal.value = true
}

function openTermRuleEdit(rule) {
    termRuleFormId.value = rule.id
    termRuleForm.value = getDefaultTermRuleForm(rule)
    showTermRuleModal.value = true
}

function tryCloseTermRuleModal() {
    if (termRuleFormSubmitting.value) {
        return
    }
    showTermRuleModal.value = false
}

function submitTermRuleForm() {
    if (termRuleFormSubmitting.value) {
        return
    }
    const payload = {
        days: Number(termRuleForm.value.days),
        institution_id: Number(termRuleForm.value.institution_id),
        category: termRuleForm.value.category || null,
        problem_type_id: termRuleForm.value.problem_type_id ? Number(termRuleForm.value.problem_type_id) : null,
        problem_nature_id: termRuleForm.value.problem_nature_id ? Number(termRuleForm.value.problem_nature_id) : null,
        is_active: termRuleForm.value.is_active,
    }
    termRuleFormSubmitting.value = true
    const options = {
        preserveScroll: true,
        onSuccess: () => { showTermRuleModal.value = false },
        onFinish: () => { termRuleFormSubmitting.value = false },
    }
    if (termRuleFormId.value) {
        router.put(route('automation.resolutionTermRules.update', termRuleFormId.value), payload, options)
    } else {
        router.post(route('automation.resolutionTermRules.store'), payload, options)
    }
}

function confirmTermRuleDelete(rule) {
    termRuleToDelete.value = rule
    showTermRuleDeleteModal.value = true
}

function doTermRuleDelete() {
    if (!termRuleToDelete.value || deleteSubmitting.value) {
        return
    }
    deleteSubmitting.value = true
    router.delete(route('automation.resolutionTermRules.destroy', termRuleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showTermRuleDeleteModal.value = false
            termRuleToDelete.value = null
        },
        onFinish: () => { deleteSubmitting.value = false },
    })
}

const termRuleCategoryOptions = computed(() => {
    const types = props.classifierTypesWithNatures || []
    return types.map((t) => ({ value: t.name, label: t.name }))
})

const termRuleRusisOptions = computed(() => {
    const category = termRuleForm.value.category
    if (!category) return []
    const types = props.classifierTypesWithNatures || []
    const typeRow = types.find((t) => t.name === category)
    if (!typeRow || !typeRow.natures?.length) return []
    return typeRow.natures.map((n) => ({ value: String(n.id), label: n.name }))
})

const termRuleRusisDisabled = computed(() => !termRuleForm.value.category)

watch(
    () => termRuleForm.value.category,
    (newCat) => {
        if (!newCat) {
            termRuleForm.value.problem_type_id = ''
            termRuleForm.value.problem_nature_id = ''
            return
        }
        const types = props.classifierTypesWithNatures || []
        const typeRow = types.find((t) => t.name === newCat)
        if (typeRow) {
            termRuleForm.value.problem_type_id = String(typeRow.id)
            const currentNatureId = termRuleForm.value.problem_nature_id
            const validNature = typeRow.natures?.some((n) => String(n.id) === currentNatureId)
            if (!validNature) termRuleForm.value.problem_nature_id = ''
        } else {
            termRuleForm.value.problem_type_id = ''
            termRuleForm.value.problem_nature_id = ''
        }
    }
)
</script>

<template>
    <div>
        <h2 class="automation-subtitle">Sprendimo terminų taisyklės</h2>
        <p class="automation-desc">
            Nustatykite, kiek dienų įstaiga turi išspręsti problemą. Terminas priskiriamas pagal įstaigą ir kategoriją.
        </p>
        <div class="automation-toolbar">
            <AppButton text="Pridėti termino taisyklę" variant="primary" as-button @click="openTermRuleCreate" />
        </div>
        <div class="automation-table-wrap">
            <table v-if="resolutionTermRules?.length" class="automation-table">
                <thead>
                    <tr>
                        <th>Dienos</th>
                        <th>Įstaiga</th>
                        <th>Kategorija</th>
                        <th>Pobūdis</th>
                        <th>Aktyvi</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in resolutionTermRules" :key="r.id">
                        <td>{{ r.days }}</td>
                        <td>{{ r.institution_name || '-' }}</td>
                        <td>{{ r.category || '-' }}</td>
                        <td>{{ r.problem_nature_name || '-' }}</td>
                        <td>{{ r.is_active ? 'Taip' : 'Ne' }}</td>
                        <td>
                            <button type="button" class="automation-link" @click="openTermRuleEdit(r)">Redaguoti</button>
                            <span class="automation-sep">|</span>
                            <button type="button" class="automation-link" @click="confirmTermRuleDelete(r)">Trinti</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="automation-empty">Nėra sprendimo termino taisyklių. Pridėkite taisyklę, kad problemoms būtų automatiškai nustatomas terminas.</p>
        </div>

        <BaseModal
            :show="showTermRuleModal"
            title="Sprendimo termino taisyklė"
            :max-width="'520px'"
            class="automation-rule-modal"
            footer-layout="column"
            @close="tryCloseTermRuleModal"
        >
            <div class="automation-form">
                <AppInput v-model.number="termRuleForm.days" type="number" label="Terminas (dienomis)" />
                <AppSelect v-model="termRuleForm.institution_id" label="Įstaiga" :required="true" placeholder="Pasirinkti">
                    <option v-for="i in institutions" :key="i.id" :value="String(i.id)">{{ i.name }}</option>
                </AppSelect>
                <AppSelect v-model="termRuleForm.category" label="Kategorija (nebūtina)" placeholder="Pasirinkti">
                    <option v-for="opt in termRuleCategoryOptions" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <AppSelect
                    v-model="termRuleForm.problem_nature_id"
                    label="Pobūdis (nebūtina)"
                    :disabled="termRuleRusisDisabled"
                    placeholder="Pasirinkti"
                >
                    <option v-for="opt in termRuleRusisOptions" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <label class="automation-form-checkbox">
                    <AppCheckbox v-model="termRuleForm.is_active" />
                    <span>Taisyklė aktyvi</span>
                </label>
            </div>
            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button :disabled="termRuleFormSubmitting" @click="tryCloseTermRuleModal" />
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="!termRuleForm.days || termRuleForm.days < 1 || !termRuleForm.institution_id"
                    :loading="termRuleFormSubmitting"
                    @click="submitTermRuleForm"
                />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showTermRuleDeleteModal"
            message="Ar tikrai norite pašalinti šią sprendimo termino taisyklę?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            :loading="deleteSubmitting"
            @close="showTermRuleDeleteModal = false"
            @confirm="doTermRuleDelete"
        />
    </div>
</template>
