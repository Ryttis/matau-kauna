import { ref, computed, watch, onBeforeUnmount, toRef } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import { formatHideAtAfterDays, formatLtDateTimeFromBackend } from '@/composables/useAutomationLtDateTime'
import {
    AUTOMATION_RESOLVED_PRIVACY_COPY,
    RESOLVED_PRIVACY_DAYS_DEFAULT,
    RESOLVED_PRIVACY_DAYS_MAX,
    RESOLVED_PRIVACY_DAYS_MIN,
    AUTOMATION_SAVE_DEBOUNCE_MS,
} from '@/constants/domain/automation'

function resolvedProblemMatchesSearchQuery(problemRow, normalizedQuery) {
    if (!normalizedQuery) {
        return true
    }
    const searchableTexts = [
        problemRow.internal_number,
        problemRow.address,
        problemRow.description_preview,
        problemRow.problem_line,
    ].map((part) => String(part || '').toLowerCase())
    return searchableTexts.some((text) => text.includes(normalizedQuery))
}

function defaultDayCountFromSetting(setting) {
    return (
        setting?.days_after_resolution
        ?? setting?.days_default
        ?? RESOLVED_PRIVACY_DAYS_DEFAULT
    )
}

export function useAutomationResolvedProblemPrivacy(props) {
    const settingFromServer = toRef(props, 'resolvedProblemPrivacySetting')
    const publicResolvedProblems = toRef(props, 'resolvedPublicProblemsList')

    const isSaving = ref(false)
    const problemSearchQuery = ref('')

    const form = ref({
        days_after_resolution: defaultDayCountFromSetting(settingFromServer.value),
        is_active: false,
    })

    const minimumDayCount = computed(
        () => settingFromServer.value?.days_min ?? RESOLVED_PRIVACY_DAYS_MIN,
    )
    const maximumDayCount = computed(
        () => settingFromServer.value?.days_max ?? RESOLVED_PRIVACY_DAYS_MAX,
    )

    const hasValidDayCount = computed(() => {
        const current = form.value.days_after_resolution
        const minBound = minimumDayCount.value
        const maxBound = maximumDayCount.value
        return (
            current != null
            && !Number.isNaN(Number(current))
            && Number(current) >= minBound
            && Number(current) <= maxBound
        )
    })

    const dayCountRangeHint = computed(() =>
        AUTOMATION_RESOLVED_PRIVACY_COPY.daysHint(
            minimumDayCount.value,
            maximumDayCount.value,
        ),
    )

    const dayCountValidationMessage = computed(() => {
        if (hasValidDayCount.value) {
            return null
        }
        const raw = form.value.days_after_resolution
        const minBound = minimumDayCount.value
        const maxBound = maximumDayCount.value
        if (raw === null || raw === '' || Number.isNaN(Number(raw))) {
            return AUTOMATION_RESOLVED_PRIVACY_COPY.daysErrorEmpty
        }
        const dayCount = Number(raw)
        if (dayCount < minBound) {
            return AUTOMATION_RESOLVED_PRIVACY_COPY.daysErrorTooLow(minBound)
        }
        if (dayCount > maxBound) {
            return AUTOMATION_RESOLVED_PRIVACY_COPY.daysErrorTooHigh(maxBound)
        }
        return null
    })

    const filteredPublicProblems = computed(() => {
        const rows = publicResolvedProblems.value || []
        const normalizedQuery = problemSearchQuery.value.trim().toLowerCase()
        if (!normalizedQuery) {
            return rows
        }
        return rows.filter((row) => resolvedProblemMatchesSearchQuery(row, normalizedQuery))
    })

    const tableDisplayRows = computed(() => {
        const daysAfterResolution = form.value.days_after_resolution
        return filteredPublicProblems.value.map((problemRow) => ({
            ...problemRow,
            problemLabel: problemRow.problem_line || String(problemRow.internal_number || ''),
            resolvedLabel: formatLtDateTimeFromBackend(problemRow.resolved_at),
            hideAtLabel: formatHideAtAfterDays(problemRow.resolved_at, daysAfterResolution),
        }))
    })

    const shouldScrollTableBody = computed(() => (filteredPublicProblems.value?.length || 0) > 5)

    function applyServerStateToForm() {
        const serverSetting = settingFromServer.value
        if (!serverSetting) {
            return
        }
        form.value = {
            days_after_resolution: defaultDayCountFromSetting(serverSetting),
            is_active: serverSetting.is_active ?? false,
        }
    }

    function persistedDayCountOnServer() {
        const serverSetting = settingFromServer.value
        if (!serverSetting) {
            return null
        }
        return Number(defaultDayCountFromSetting(serverSetting))
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

    function persistSettingsToServer() {
        if (isSaving.value) {
            return
        }
        if (form.value.is_active && !hasValidDayCount.value) {
            return
        }
        isSaving.value = true
        router.put(
            route('automation.resolvedProblemPrivacySetting.update'),
            {
                days_after_resolution: form.value.days_after_resolution,
                is_active: form.value.is_active,
            },
            inertiaVisitOptions,
        )
    }

    function handleAutomaticHidingToggle(wantsAutomationEnabled) {
        if (isSaving.value) {
            return
        }
        if (wantsAutomationEnabled && !hasValidDayCount.value) {
            return
        }
        form.value.is_active = wantsAutomationEnabled
        persistSettingsToServer()
    }

    const schedulePersistDayCountIfAutomationEnabled = debounce(() => {
        if (isSaving.value || !form.value.is_active || !hasValidDayCount.value) {
            return
        }
        const enteredDayCount = Number(form.value.days_after_resolution)
        if (Number.isNaN(enteredDayCount)) {
            return
        }
        if (enteredDayCount === persistedDayCountOnServer()) {
            return
        }
        persistSettingsToServer()
    }, AUTOMATION_SAVE_DEBOUNCE_MS)

    watch(
        () => form.value.days_after_resolution,
        () => {
            if (form.value.is_active && hasValidDayCount.value) {
                schedulePersistDayCountIfAutomationEnabled()
            }
        },
    )

    watch(
        () => form.value.is_active,
        (automationIsEnabled) => {
            if (!automationIsEnabled) {
                schedulePersistDayCountIfAutomationEnabled.cancel()
            }
        },
    )

    watch(settingFromServer, applyServerStateToForm, { immediate: true })

    onBeforeUnmount(() => {
        schedulePersistDayCountIfAutomationEnabled.cancel()
    })

    return {
        form,
        isSaving,
        problemSearchQuery,
        minimumDayCount,
        maximumDayCount,
        hasValidDayCount,
        dayCountRangeHint,
        dayCountValidationMessage,
        filteredPublicProblems,
        tableDisplayRows,
        shouldScrollTableBody,
        publicResolvedProblems,
        handleAutomaticHidingToggle,
        copy: AUTOMATION_RESOLVED_PRIVACY_COPY,
    }
}
