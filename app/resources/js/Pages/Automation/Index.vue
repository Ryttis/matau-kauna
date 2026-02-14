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
})

const showFormModal = ref(false)
const showDeleteModal = ref(false)
const formRuleId = ref(null)
const ruleToDelete = ref(null)
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
