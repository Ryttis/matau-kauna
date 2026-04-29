import { ref, computed, watch, onBeforeUnmount, toRef } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import {
    AUTOMATION_INACTIVE_REPORTER_COPY,
    AUTOMATION_SAVE_DEBOUNCE_MS,
    INACTIVE_REPORTER_DAYS_DEFAULT,
    INACTIVE_REPORTER_DAYS_MAX,
    INACTIVE_REPORTER_DAYS_MIN,
    INACTIVITY_DAYS_FALLBACK,
} from '@/constants/domain/automation'

function inactiveReporterMatchesSearch(row, normalizedQuery) {
    if (!normalizedQuery) {
        return true
    }
    const name = String(row.name || '').toLowerCase()
    const email = String(row.email || '').toLowerCase()
    return name.includes(normalizedQuery) || email.includes(normalizedQuery)
}

function defaultInactivityDaysFromSetting(setting) {
    return (
        setting?.inactivity_days
        ?? setting?.inactivity_days_default
        ?? INACTIVE_REPORTER_DAYS_DEFAULT
        ?? INACTIVITY_DAYS_FALLBACK
    )
}

export function useAutomationInactiveReporter(props) {
    const settingFromServer = toRef(props, 'inactiveReporterSetting')
    const inactiveReportersFromServer = toRef(props, 'inactiveReportersList')

    const isSaving = ref(false)
    const userSearchQuery = ref('')

    const form = ref({
        inactivity_days: defaultInactivityDaysFromSetting(settingFromServer.value),
        is_active: false,
    })

    const minimumInactivityDays = computed(
        () => settingFromServer.value?.inactivity_days_min ?? INACTIVE_REPORTER_DAYS_MIN,
    )
    const maximumInactivityDays = computed(
        () => settingFromServer.value?.inactivity_days_max ?? INACTIVE_REPORTER_DAYS_MAX,
    )

    const hasValidInactivityDayCount = computed(() => {
        const current = form.value.inactivity_days
        const minBound = minimumInactivityDays.value
        const maxBound = maximumInactivityDays.value
        return (
            current != null
            && !Number.isNaN(Number(current))
            && Number(current) >= minBound
            && Number(current) <= maxBound
        )
    })

    const inactivityDayCountRangeHint = computed(() =>
        AUTOMATION_INACTIVE_REPORTER_COPY.daysHint(
            minimumInactivityDays.value,
            maximumInactivityDays.value,
        ),
    )

    const inactivityDayCountValidationMessage = computed(() => {
        if (hasValidInactivityDayCount.value) {
            return null
        }
        const raw = form.value.inactivity_days
        const minBound = minimumInactivityDays.value
        const maxBound = maximumInactivityDays.value
        if (raw === null || raw === '' || Number.isNaN(Number(raw))) {
            return AUTOMATION_INACTIVE_REPORTER_COPY.daysErrorEmpty
        }
        const dayCount = Number(raw)
        if (dayCount < minBound) {
            return AUTOMATION_INACTIVE_REPORTER_COPY.daysErrorTooLow(minBound)
        }
        if (dayCount > maxBound) {
            return AUTOMATION_INACTIVE_REPORTER_COPY.daysErrorTooHigh(maxBound)
        }
        return null
    })

    const reportersWithPositiveInactiveDays = computed(() =>
        (inactiveReportersFromServer.value || []).filter(
            (row) => Number(row.inactive_days || 0) > 0,
        ),
    )

    const filteredInactiveReporterRows = computed(() => {
        const normalizedQuery = userSearchQuery.value.trim().toLowerCase()
        return reportersWithPositiveInactiveDays.value.filter((row) =>
            inactiveReporterMatchesSearch(row, normalizedQuery),
        )
    })

    const shouldScrollReporterTable = computed(
        () => (filteredInactiveReporterRows.value?.length || 0) > 5,
    )

    function applyServerStateToForm() {
        const serverSetting = settingFromServer.value
        if (!serverSetting) {
            return
        }
        form.value = {
            inactivity_days: defaultInactivityDaysFromSetting(serverSetting),
            is_active: serverSetting.is_active ?? false,
        }
    }

    function persistedInactivityDaysOnServer() {
        const serverSetting = settingFromServer.value
        if (!serverSetting) {
            return null
        }
        return Number(defaultInactivityDaysFromSetting(serverSetting))
    }

    const inertiaVisitOptions = {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false
        },
        onError: () => {
            applyServerStateToForm()
        },
    }

    function persistInactiveReporterSettings() {
        if (isSaving.value) {
            return
        }
        if (form.value.is_active && !hasValidInactivityDayCount.value) {
            return
        }
        isSaving.value = true
        router.put(
            route('automation.inactiveReporterSetting.update'),
            {
                inactivity_days: form.value.inactivity_days,
                is_active: form.value.is_active,
            },
            inertiaVisitOptions,
        )
    }

    function handleAutomaticDeletionToggle(wantsAutomationEnabled) {
        if (isSaving.value) {
            return
        }
        if (wantsAutomationEnabled && !hasValidInactivityDayCount.value) {
            return
        }
        form.value.is_active = wantsAutomationEnabled
        persistInactiveReporterSettings()
    }

    const schedulePersistInactivityDaysIfAutomationEnabled = debounce(() => {
        if (isSaving.value || !form.value.is_active || !hasValidInactivityDayCount.value) {
            return
        }
        const enteredDayCount = Number(form.value.inactivity_days)
        if (Number.isNaN(enteredDayCount)) {
            return
        }
        if (enteredDayCount === persistedInactivityDaysOnServer()) {
            return
        }
        persistInactiveReporterSettings()
    }, AUTOMATION_SAVE_DEBOUNCE_MS)

    watch(
        () => form.value.inactivity_days,
        () => {
            if (form.value.is_active && hasValidInactivityDayCount.value) {
                schedulePersistInactivityDaysIfAutomationEnabled()
            }
        },
    )

    watch(
        () => form.value.is_active,
        (automationIsEnabled) => {
            if (!automationIsEnabled) {
                schedulePersistInactivityDaysIfAutomationEnabled.cancel()
            }
        },
    )

    watch(settingFromServer, applyServerStateToForm, { immediate: true })

    onBeforeUnmount(() => {
        schedulePersistInactivityDaysIfAutomationEnabled.cancel()
    })

    return {
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
        copy: AUTOMATION_INACTIVE_REPORTER_COPY,
    }
}
