<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'

const page = usePage()
const showSuccessModal = ref(!!page.props.flash?.success)
watch(() => page.props.flash?.success, (msg) => { if (msg) showSuccessModal.value = true }, { immediate: true })

const props = defineProps({
    rules: Array,
    categories: Array,
    institutions: Array,
    publishRules: Array,
    resolutionTermRules: Array,
    classifierTypesWithNatures: Array,
    statusOptions: Array,
})

const showFormModal = ref(false)
const showDeleteModal = ref(false)
const formRuleId = ref(null)
const ruleToDelete = ref(null)

const showPublishFormModal = ref(false)
const showPublishDeleteModal = ref(false)
const publishFormId = ref(null)
const publishRuleToDelete = ref(null)

const showTermRuleModal = ref(false)
const showTermRuleDeleteModal = ref(false)
const termRuleFormId = ref(null)
const termRuleToDelete = ref(null)
const defaultInstitutionId = computed(() =>
    props.institutions?.length ? String(props.institutions[0].id) : ''
)

const categoryOptions = computed(() => {
    const options = [{ value: '', label: 'Visos kategorijos' }]
    ;(props.categories || []).filter(Boolean).forEach((c) => options.push({ value: c, label: c }))
    return options
})

const statusOptionsForPublish = computed(() => {
    const opts = [{ value: '', label: 'Bet kuri būsena' }]
    ;(props.statusOptions || []).forEach((o) => {
        opts.push({ value: o.value ?? o, label: o.label ?? o.value ?? o })
    })
    return opts
})

function getDefaultForm(rule = null) {
    const defaultId = defaultInstitutionId.value
    return {
        name: rule?.name ?? '',
        category: rule?.category ?? '',
        keyword: rule?.keyword ?? '',
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
        keyword: form.value.keyword || null,
        institution_id: Number(form.value.institution_id),
        is_active: form.value.is_active,
    }
}

function submitForm() {
    const payload = buildPayload()
    const options = {
        preserveScroll: true,
        onSuccess: () => { showFormModal.value = false },
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
    if (!ruleToDelete.value) return
    router.delete(route('automation.destroy', ruleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            ruleToDelete.value = null
        },
    })
}

const publishForm = ref({ category: '', status: '', is_active: true })

function getDefaultPublishForm(rule = null) {
    return {
        category: rule?.category ?? '',
        status: rule?.status ?? '',
        is_active: rule?.is_active ?? true,
    }
}

function openPublishCreate() {
    publishFormId.value = null
    publishForm.value = getDefaultPublishForm()
    showPublishFormModal.value = true
}

function openPublishEdit(rule) {
    publishFormId.value = rule.id
    publishForm.value = getDefaultPublishForm(rule)
    showPublishFormModal.value = true
}

function submitPublishForm() {
    const payload = {
        category: publishForm.value.category || null,
        status: publishForm.value.status || null,
        is_active: publishForm.value.is_active,
    }
    const options = { preserveScroll: true, onSuccess: () => { showPublishFormModal.value = false } }
    if (publishFormId.value) {
        router.put(route('automation.publishRules.update', publishFormId.value), payload, options)
    } else {
        router.post(route('automation.publishRules.store'), payload, options)
    }
}

function confirmPublishDelete(rule) {
    publishRuleToDelete.value = rule
    showPublishDeleteModal.value = true
}

function doPublishDelete() {
    if (!publishRuleToDelete.value) return
    router.delete(route('automation.publishRules.destroy', publishRuleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPublishDeleteModal.value = false
            publishRuleToDelete.value = null
        },
    })
}

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

function submitTermRuleForm() {
    const payload = {
        days: Number(termRuleForm.value.days),
        institution_id: termRuleForm.value.institution_id ? Number(termRuleForm.value.institution_id) : null,
        category: termRuleForm.value.category || null,
        problem_type_id: termRuleForm.value.problem_type_id ? Number(termRuleForm.value.problem_type_id) : null,
        problem_nature_id: termRuleForm.value.problem_nature_id ? Number(termRuleForm.value.problem_nature_id) : null,
        is_active: termRuleForm.value.is_active,
    }
    const options = { preserveScroll: true, onSuccess: () => { showTermRuleModal.value = false } }
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
    if (!termRuleToDelete.value) return
    router.delete(route('automation.resolutionTermRules.destroy', termRuleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showTermRuleDeleteModal.value = false
            termRuleToDelete.value = null
        },
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
    <Head title="Automatizacija" />
    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="page.props.flash?.success ?? ''"
            @close="showSuccessModal = false"
        />
        <div class="automation-page">
            <h1 class="automation-title">Automatizacija</h1>
            <p class="automation-desc">
                Nustatykite taisykles, pagal kurias naujai užregistruotos problemos bus automatiškai priskiriamos įstaigoms pagal kategoriją.
            </p>
            <div class="automation-toolbar">
                <AppButton text="Pridėti taisyklę" variant="primary" as-button @click="openCreate" />
            </div>
            <div class="automation-table-wrap">
                <table class="automation-table" v-if="rules?.length">
                    <thead>
                        <tr>
                            <th>Pavadinimas</th>
                            <th>Kategorija</th>
                            <th>Raktažodis</th>
                            <th>Įstaiga</th>
                            <th>Aktyvi</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rules" :key="r.id">
                            <td>{{ r.name || '—' }}</td>
                            <td>{{ r.category || '—' }}</td>
                            <td>{{ r.keyword || '—' }}</td>
                            <td>{{ r.institution_name || '—' }}</td>
                            <td>{{ r.is_active ? 'Taip' : 'Ne' }}</td>
                            <td>
                                <button type="button" class="automation-link" @click="openEdit(r)">Redaguoti</button>
                                <span class="automation-sep">|</span>
                                <button type="button" class="automation-link" @click="confirmDelete(r)">Trinti</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="automation-empty">Nėra taisyklių. Pridėkite taisyklę, kad problemos būtų automatiškai priskiriamos įstaigoms.</p>
            </div>

            <h2 class="automation-subtitle">Publikavimo taisyklės</h2>
            <p class="automation-desc">
                Pagal kategoriją ir būseną nustatykite, kada problemos bus automatiškai paskelbiamos viešai (po būsenos atnaujinimo).
            </p>
            <div class="automation-toolbar">
                <AppButton text="Pridėti publikavimo taisyklę" variant="primary" as-button @click="openPublishCreate" />
            </div>
            <div class="automation-table-wrap">
                <table class="automation-table" v-if="publishRules?.length">
                    <thead>
                        <tr>
                            <th>Kategorija</th>
                            <th>Būsena</th>
                            <th>Aktyvi</th>
                            <th>Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in publishRules" :key="r.id">
                            <td>{{ r.category || 'Bet kuri' }}</td>
                            <td>{{ r.status || 'Bet kuri' }}</td>
                            <td>{{ r.is_active ? 'Taip' : 'Ne' }}</td>
                            <td>
                                <button type="button" class="automation-link" @click="openPublishEdit(r)">Redaguoti</button>
                                <span class="automation-sep">|</span>
                                <button type="button" class="automation-link" @click="confirmPublishDelete(r)">Trinti</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="automation-empty">Nėra publikavimo taisyklių.</p>
            </div>

            <h2 class="automation-subtitle">Sprendimo terminų taisyklės</h2>
            <p class="automation-desc">
                Nustatykite terminus dienomis pagal įstaigą ir (arba) klasifikatoriaus reikšmę. Kai problema priskiriama įstaigai (automatiškai ar rankiniu būdu), jai bus priskirtas atitinkamas atlikimo terminas, jei taisyklė atitinka.
            </p>
            <div class="automation-toolbar">
                <AppButton text="Pridėti termino taisyklę" variant="primary" as-button @click="openTermRuleCreate" />
            </div>
            <div class="automation-table-wrap">
                <table class="automation-table" v-if="resolutionTermRules?.length">
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
                            <td>{{ r.institution_name || '—' }}</td>
                            <td>{{ r.category || '—' }}</td>
                            <td>{{ r.problem_nature_name || '—' }}</td>
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
        </div>

        <BaseModal
            :show="showFormModal"
            title="Taisyklė"
            :max-width="'520px'"
            class="automation-rule-modal"
            @close="showFormModal = false"
        >
            <div class="automation-form">
                <AppInput v-model="form.name" label="Pavadinimas (nebūtina)" placeholder="pvz. Duobės" />
                <AppSelect v-model="form.category" label="Kategorija" :placeholder="''">
                    <option v-for="opt in categoryOptions" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <AppInput v-model="form.keyword" label="Raktažodis aprašyme (nebūtina)" placeholder="pvz. apšvietimas" />
                <AppSelect v-model="form.institution_id" label="Įstaiga" placeholder="Pasirinkite įstaigą">
                    <option v-for="i in institutions" :key="i.id" :value="String(i.id)">{{ i.name }}</option>
                </AppSelect>
                <label class="automation-form-checkbox">
                    <AppCheckbox v-model="form.is_active" />
                    <span>Taisyklė aktyvi</span>
                </label>
            </div>
            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="showFormModal = false" />
                <AppButton text="Išsaugoti" variant="primary" as-button @click="submitForm" />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showDeleteModal"
            message="Ar tikrai norite pašalinti šią taisyklę?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            @close="showDeleteModal = false"
            @confirm="doDelete"
        />

        <BaseModal
            :show="showPublishFormModal"
            title="Publikavimo taisyklė"
            :max-width="'520px'"
            class="automation-rule-modal"
            @close="showPublishFormModal = false"
        >
            <div class="automation-form">
                <AppSelect v-model="publishForm.category" label="Kategorija" :placeholder="''">
                    <option v-for="opt in categoryOptions" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <AppSelect v-model="publishForm.status" label="Būsena" :placeholder="''">
                    <option v-for="opt in statusOptionsForPublish" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <label class="automation-form-checkbox">
                    <AppCheckbox v-model="publishForm.is_active" />
                    <span>Taisyklė aktyvi</span>
                </label>
            </div>
            <template #footer>
                <AppButton text="Atšaukti" variant="secondary" as-button @click="showPublishFormModal = false" />
                <AppButton text="Išsaugoti" variant="primary" as-button @click="submitPublishForm" />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showPublishDeleteModal"
            message="Ar tikrai norite pašalinti šią publikavimo taisyklę?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            @close="showPublishDeleteModal = false"
            @confirm="doPublishDelete"
        />

        <BaseModal
            :show="showTermRuleModal"
            title="Sprendimo termino taisyklė"
            :max-width="'520px'"
            class="automation-rule-modal"
            @close="showTermRuleModal = false"
        >
            <div class="automation-form">
                <AppInput v-model.number="termRuleForm.days" type="number" label="Terminas (dienomis)" />
                <AppSelect v-model="termRuleForm.institution_id" label="Įstaiga (nebūtina)" placeholder="Pasirinkti">
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
                <AppButton text="Atšaukti" variant="secondary" as-button @click="showTermRuleModal = false" />
                <AppButton text="Išsaugoti" variant="primary" as-button :disabled="!termRuleForm.days || termRuleForm.days < 1" @click="submitTermRuleForm" />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showTermRuleDeleteModal"
            message="Ar tikrai norite pašalinti šią sprendimo termino taisyklę?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            @close="showTermRuleDeleteModal = false"
            @confirm="doTermRuleDelete"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.automation-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 24px 32px;
}
.automation-title {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px 0;
    font-family: 'Inter', sans-serif;
}
.automation-desc {
    font-size: 14px;
    color: #666;
    margin: 0 0 24px 0;
    line-height: 1.5;
}
.automation-subtitle {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 32px 0 8px 0;
    font-family: 'Inter', sans-serif;
}
.automation-toolbar {
    margin-bottom: 20px;
}
.automation-table-wrap {
    overflow-x: auto;
}
.automation-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.automation-table th,
.automation-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}
.automation-table th {
    font-weight: 600;
    color: #333;
    background: #f6f7f9;
}
.automation-table td {
    color: #333;
}
.automation-link {
    background: none;
    border: none;
    color: #1946A0;
    cursor: pointer;
    font-size: 14px;
    padding: 0;
    text-decoration: underline;
}
.automation-link:hover {
    color: #0d3580;
}
.automation-sep {
    margin: 0 6px;
    color: #999;
}
.automation-empty {
    color: #666;
    font-size: 14px;
    margin: 0;
    padding: 24px 0;
}
.automation-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.automation-form-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}

.automation-rule-modal :deep(.modal-footer) {
    flex-direction: row;
    flex-wrap: nowrap;
    gap: 12px;
}
.automation-rule-modal :deep(.modal-footer .app-button) {
    width: auto;
    flex: 0 0 auto;
}
</style>
