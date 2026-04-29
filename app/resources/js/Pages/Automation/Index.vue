<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AutomationAssignmentSection from '@/Components/Automation/AutomationAssignmentSection.vue'
import AutomationPublishSection from '@/Components/Automation/AutomationPublishSection.vue'
import AutomationResolutionTermsSection from '@/Components/Automation/AutomationResolutionTermsSection.vue'
import AutomationInactiveReporterSection from '@/Components/Automation/AutomationInactiveReporterSection.vue'
import AutomationResolvedProblemPrivacySection from '@/Components/Automation/AutomationResolvedProblemPrivacySection.vue'
import { AUTOMATION_SECTIONS } from '@/constants/domain/automation'
import '@/Components/Automation/automation-shared.css'

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
    resolvedProblemPrivacySetting: Object,
    resolvedPublicProblemsList: Array,
})

const activeSection = ref('assignment')
</script>

<template>
    <Head title="Automatizacija" />
    <AuthenticatedLayout>
        <div class="automation-page">
            <h1 id="automation-page-title" class="automation-title">Automatizacija</h1>
            <p class="automation-desc">Automatizavimo taisyklių valdymas problemų skyrimui, viešinimui ir terminams.</p>
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
            <AutomationResolvedProblemPrivacySection
                v-if="activeSection === 'resolved_problem_privacy'"
                :resolved-problem-privacy-setting="resolvedProblemPrivacySetting"
                :resolved-public-problems-list="resolvedPublicProblemsList"
            />
        </div>
    </AuthenticatedLayout>
</template>
