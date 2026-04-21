<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AutomationAssignmentSection from '@/Components/Automation/AutomationAssignmentSection.vue'
import AutomationPublishSection from '@/Components/Automation/AutomationPublishSection.vue'
import AutomationResolutionTermsSection from '@/Components/Automation/AutomationResolutionTermsSection.vue'
import AutomationInactiveReporterSection from '@/Components/Automation/AutomationInactiveReporterSection.vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { AUTOMATION_SECTIONS } from '@/constants/domain/automation'
import '@/Components/Automation/automation-shared.css'

const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()

defineProps({
    rules: Array,
    categories: Array,
    institutions: Array,
    publishRules: Array,
    resolutionTermRules: Array,
    classifierTypesWithNatures: Array,
    statusOptions: Array,
    inactiveReporterSetting: Object,
    inactiveReportersList: Array,
})

const activeSection = ref('assignment')
</script>

<template>
    <Head title="Automatizacija" />
    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="automation-page">
            <h1 id="automation-page-title" class="automation-title">Automatizacija</h1>
            <div
                class="automation-section-select-wrap"
                role="group"
                aria-labelledby="automation-page-title"
            >
                <AppSelect
                    v-model="activeSection"
                    class="automation-section-select"
                    variant="white"
                    :rounded="true"
                >
                    <option v-for="opt in AUTOMATION_SECTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
            </div>

            <AutomationAssignmentSection
                v-if="activeSection === 'assignment'"
                :rules="rules"
                :categories="categories"
                :institutions="institutions"
            />
            <AutomationPublishSection
                v-if="activeSection === 'publish'"
                :publish-rules="publishRules"
                :categories="categories"
                :status-options="statusOptions"
            />
            <AutomationResolutionTermsSection
                v-if="activeSection === 'resolution_term'"
                :resolution-term-rules="resolutionTermRules"
                :institutions="institutions"
                :classifier-types-with-natures="classifierTypesWithNatures"
            />
            <AutomationInactiveReporterSection
                v-if="activeSection === 'inactive_reporter'"
                :inactive-reporter-setting="inactiveReporterSetting"
                :inactive-reporters-list="inactiveReportersList"
            />
        </div>
    </AuthenticatedLayout>
</template>
