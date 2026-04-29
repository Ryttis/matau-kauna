<script setup>
import { Link } from '@inertiajs/vue3'
import AppInput from '@/Components/App/AppInput.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import { useAutomationResolvedProblemPrivacy } from '@/composables/useAutomationResolvedProblemPrivacy'

const props = defineProps({
    resolvedProblemPrivacySetting: Object,
    resolvedPublicProblemsList: Array,
})

const {
    form,
    isSaving,
    problemSearchQuery,
    minimumDayCount,
    maximumDayCount,
    dayCountRangeHint,
    dayCountValidationMessage,
    filteredPublicProblems,
    tableDisplayRows,
    shouldScrollTableBody,
    publicResolvedProblems,
    handleAutomaticHidingToggle,
    copy,
} = useAutomationResolvedProblemPrivacy(props)
</script>

<template>
    <div class="automation-resolved-privacy">
        <h2 class="automation-subtitle">
            {{ copy.sectionTitle }}
        </h2>
        <p class="automation-desc automation-resolved-lead">
            {{ copy.leadParagraph }}
        </p>
        <div class="automation-resolved-switch-line">
            <span class="automation-resolved-switch-label">{{ copy.switchLabel }}</span>
            <AppSwitch
                :model-value="form.is_active"
                :disabled="isSaving"
                @update:model-value="handleAutomaticHidingToggle"
            />
        </div>
        <div class="automation-inactive-settings">
            <AppInput
                v-model.number="form.days_after_resolution"
                type="number"
                :label="copy.daysFieldLabel"
                :min="minimumDayCount"
                :max="maximumDayCount"
            />
            <p class="automation-form-hint">{{ dayCountRangeHint }}</p>
            <p v-if="dayCountValidationMessage" class="automation-form-error">
                {{ dayCountValidationMessage }}
            </p>
        </div>
        <div v-if="publicResolvedProblems?.length" class="automation-inactive-table-block">
            <AppInput
                v-model="problemSearchQuery"
                type="search"
                :label="copy.searchLabel"
                :placeholder="copy.searchPlaceholder"
                class="automation-inactive-search"
            />
            <div
                class="automation-table-wrap automation-resolved-table-wrap"
                :class="{ 'automation-inactive-table-scroll': shouldScrollTableBody }"
            >
                <table class="automation-table">
                    <thead>
                        <tr>
                            <th class="automation-col-problem">{{ copy.tableColumnProblem }}</th>
                            <th>{{ copy.tableColumnResolvedAt }}</th>
                            <th>{{ copy.tableColumnHideAt }}</th>
                            <th class="automation-col-action" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="displayRow in tableDisplayRows" :key="displayRow.id">
                            <td
                                class="automation-col-problem"
                                :title="displayRow.problemLabel"
                            >
                                {{ displayRow.problemLabel }}
                            </td>
                            <td>{{ displayRow.resolvedLabel }}</td>
                            <td>{{ displayRow.hideAtLabel }}</td>
                            <td class="automation-col-action">
                                <Link
                                    :href="route('problems.show', displayRow.id)"
                                    class="automation-resolved-view-link"
                                >
                                    {{ copy.viewProblem }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="filteredPublicProblems.length === 0" class="automation-empty automation-inactive-no-match">
                {{ copy.noSearchMatches }}
            </p>
        </div>
        <p v-else class="automation-empty">
            {{ copy.noPublicResolvedProblems }}
        </p>
    </div>
</template>

<style scoped>
.automation-resolved-lead {
    margin-bottom: 16px;
}

.automation-resolved-switch-line {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px 14px;
    margin-bottom: 24px;
}

.automation-resolved-switch-label {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text, #333);
}
</style>
