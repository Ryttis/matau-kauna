<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppButton from '@/Components/App/AppButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import ConfirmModal from '@/Components/App/ConfirmModal.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'

const props = defineProps({
    publishRules: Array,
    categories: Array,
    statusOptions: Array,
})

const showPublishFormModal = ref(false)
const showPublishDeleteModal = ref(false)
const publishFormId = ref(null)
const publishRuleToDelete = ref(null)
const publishFormSubmitting = ref(false)
const deleteSubmitting = ref(false)

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

function tryClosePublishRuleModal() {
    if (publishFormSubmitting.value) {
        return
    }
    showPublishFormModal.value = false
}

function submitPublishForm() {
    if (publishFormSubmitting.value) {
        return
    }
    const payload = {
        category: publishForm.value.category || null,
        status: publishForm.value.status || null,
        is_active: publishForm.value.is_active,
    }
    publishFormSubmitting.value = true
    const options = {
        preserveScroll: true,
        onSuccess: () => { showPublishFormModal.value = false },
        onFinish: () => { publishFormSubmitting.value = false },
    }
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
    if (!publishRuleToDelete.value || deleteSubmitting.value) {
        return
    }
    deleteSubmitting.value = true
    router.delete(route('automation.publishRules.destroy', publishRuleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPublishDeleteModal.value = false
            publishRuleToDelete.value = null
        },
        onFinish: () => { deleteSubmitting.value = false },
    })
}
</script>

<template>
    <div>
        <h2 class="automation-subtitle">Publikavimo taisyklės</h2>
        <p class="automation-desc">
            Nustatykite, kada problemos bus paskelbiamos viešai.
        </p>
        <div class="automation-toolbar">
            <AppButton text="Pridėti publikavimo taisyklę" variant="primary" as-button @click="openPublishCreate" />
        </div>
        <div class="automation-table-wrap">
            <table v-if="publishRules?.length" class="automation-table">
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
                        <td>
                            <StatusBadge v-if="r.status" :status="r.status" size="small" />
                            <span v-else>Bet kuri</span>
                        </td>
                        <td>{{ r.is_active ? 'Taip' : 'Ne' }}</td>
                        <td>
                            <button type="button" class="automation-link" @click="openPublishEdit(r)">Redaguoti</button>
                            <span class="automation-sep">|</span>
                            <button type="button" class="automation-link automation-link--danger" @click="confirmPublishDelete(r)">Trinti</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="automation-empty">Nėra publikavimo taisyklių.</p>
        </div>

        <BaseModal
            :show="showPublishFormModal"
            title="Publikavimo taisyklė"
            :max-width="'520px'"
            class="automation-rule-modal"
            footer-layout="column"
            @close="tryClosePublishRuleModal"
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
                <AppButton text="Atšaukti" variant="secondary" as-button :disabled="publishFormSubmitting" @click="tryClosePublishRuleModal" />
                <AppButton text="Išsaugoti" variant="primary" as-button :loading="publishFormSubmitting" @click="submitPublishForm" />
            </template>
        </BaseModal>

        <ConfirmModal
            :open="showPublishDeleteModal"
            message="Ar tikrai norite pašalinti šią publikavimo taisyklę?"
            confirm-text="Trinti"
            cancel-text="Atšaukti"
            confirm-variant="danger"
            :loading="deleteSubmitting"
            @close="showPublishDeleteModal = false"
            @confirm="doPublishDelete"
        />
    </div>
</template>
