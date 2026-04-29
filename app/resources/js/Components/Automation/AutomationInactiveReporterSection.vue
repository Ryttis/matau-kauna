<script setup>
import AppInput from '@/Components/App/AppInput.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import { useAutomationInactiveReporter } from '@/composables/useAutomationInactiveReporter'

const props = defineProps({
    inactiveReporterSetting: Object,
    inactiveReportersList: Array,
})

const {
    form,
    isSaving,
    userSearchQuery,
    minimumInactivityDays,
    maximumInactivityDays,
    inactivityDayCountRangeHint,
    inactivityDayCountValidationMessage,
    filteredInactiveReporterRows,
    shouldScrollReporterTable,
    inactiveReportersFromServer,
    handleAutomaticDeletionToggle,
    copy,
} = useAutomationInactiveReporter(props)
</script>

<template>
    <div class="automation-inactive-reporter">
        <h2 class="automation-subtitle">
            {{ copy.sectionTitle }}
        </h2>
        <p class="automation-desc automation-inactive-reporter-lead">
            {{ copy.leadParagraph }}
        </p>
        <div class="automation-inactive-reporter-switch-line">
            <span class="automation-inactive-reporter-switch-label">{{ copy.switchLabel }}</span>
            <AppSwitch
                :model-value="form.is_active"
                :disabled="isSaving"
                @update:model-value="handleAutomaticDeletionToggle"
            />
        </div>
        <div class="automation-inactive-settings">
            <AppInput
                v-model.number="form.inactivity_days"
                type="number"
                :label="copy.daysFieldLabel"
                :min="minimumInactivityDays"
                :max="maximumInactivityDays"
            />
            <p class="automation-form-hint">{{ inactivityDayCountRangeHint }}</p>
            <p v-if="inactivityDayCountValidationMessage" class="automation-form-error">
                {{ inactivityDayCountValidationMessage }}
            </p>
        </div>
        <div v-if="inactiveReportersFromServer?.length" class="automation-inactive-table-block">
            <AppInput
                v-model="userSearchQuery"
                type="search"
                :label="copy.searchLabel"
                :placeholder="copy.searchPlaceholder"
                class="automation-inactive-search"
            />
            <div
                class="automation-table-wrap"
                :class="{ 'automation-inactive-table-scroll': shouldScrollReporterTable }"
            >
                <table class="automation-table">
                    <thead>
                        <tr>
                            <th>{{ copy.tableColumnUser }}</th>
                            <th>{{ copy.tableColumnLastLogin }}</th>
                            <th>{{ copy.tableColumnInactiveDays }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredInactiveReporterRows" :key="row.id">
                            <td>{{ row.name }} <span class="automation-email">({{ row.email }})</span></td>
                            <td>{{ row.last_login_at ?? '-' }}</td>
                            <td>
                                <template v-if="row.inactive_days != null">{{ row.inactive_days }}</template>
                                <template v-else>-</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="filteredInactiveReporterRows.length === 0" class="automation-empty automation-inactive-no-match">
                {{ copy.noSearchMatches }}
            </p>
        </div>
        <p v-else class="automation-empty">
            {{ copy.noInactiveReporters }}
        </p>
    </div>
</template>

<style scoped>
.automation-inactive-reporter-lead {
    margin-bottom: 16px;
}

.automation-inactive-reporter-switch-line {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px 14px;
    margin-bottom: 24px;
}

.automation-inactive-reporter-switch-label {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text, #333);
}
</style>
