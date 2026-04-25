<script setup>
import { ref, computed, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import { formatHideAtAfterDays, formatLtDateTimeFromBackend } from '@/composables/useAutomationLtDateTime'
import { INACTIVITY_DAYS_FALLBACK } from '@/constants/domain/automation'

const props = defineProps({
    resolvedProblemPrivacySetting: Object,
    resolvedPublicProblemsList: Array,
})

const submitting = ref(false)

const form = ref({
    days_after_resolution: props.resolvedProblemPrivacySetting?.days_default ?? INACTIVITY_DAYS_FALLBACK,
    is_active: false,
})

const minDays = computed(() => props.resolvedProblemPrivacySetting?.days_min ?? 30)
const maxDays = computed(() => props.resolvedProblemPrivacySetting?.days_max ?? 3650)

const canSave = computed(() => {
    const f = form.value
    const minD = minDays.value
    const maxD = maxDays.value
    return f.days_after_resolution != null && f.days_after_resolution >= minD && f.days_after_resolution <= maxD
})

const daysRangeHint = computed(() => {
    const minD = minDays.value
    const maxD = maxDays.value
    return `Leistina reikšmė: nuo ${minD} iki ${maxD} dienų.`
})

const daysInvalidHint = computed(() => {
    if (canSave.value) {
        return null
    }
    const raw = form.value.days_after_resolution
    const minD = minDays.value
    const maxD = maxDays.value
    if (raw === null || raw === '' || Number.isNaN(Number(raw))) {
        return 'Įveskite terminą dienomis.'
    }
    const n = Number(raw)
    if (n < minD) {
        return `Per mažas terminas. Minimali reikšmė: ${minD} dienų.`
    }
    if (n > maxD) {
        return `Per didelis terminas. Maksimali reikšmė: ${maxD} dienų.`
    }
    return null
})

const search = ref('')

const filteredRows = computed(() => {
    let rows = props.resolvedPublicProblemsList || []
    const q = search.value.trim().toLowerCase()
    if (!q) return rows
    return rows.filter((row) => {
        const num = String(row.internal_number || '').toLowerCase()
        const addr = String(row.address || '').toLowerCase()
        const desc = String(row.description_preview || '').toLowerCase()
        const line = String(row.problem_line || '').toLowerCase()
        return num.includes(q) || addr.includes(q) || desc.includes(q) || line.includes(q)
    })
})

const tableRows = computed(() => {
    const days = form.value.days_after_resolution
    return filteredRows.value.map((row) => ({
        ...row,
        problemLabel: row.problem_line || String(row.internal_number || ''),
        resolvedLabel: formatLtDateTimeFromBackend(row.resolved_at),
        hideAtLabel: formatHideAtAfterDays(row.resolved_at, days),
    }))
})

const tableScrollable = computed(() => (filteredRows.value?.length || 0) > 5)

function syncFormFromProps() {
    const s = props.resolvedProblemPrivacySetting
    if (!s) return
    form.value = {
        days_after_resolution: s.days_after_resolution ?? s.days_default ?? INACTIVITY_DAYS_FALLBACK,
        is_active: s.is_active ?? false,
    }
}

function submitForm() {
    if (submitting.value) {
        return
    }
    submitting.value = true
    router.put(route('automation.resolvedProblemPrivacySetting.update'), {
        days_after_resolution: form.value.days_after_resolution,
        is_active: form.value.is_active,
    }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = false },
    })
}

watch(() => props.resolvedProblemPrivacySetting, syncFormFromProps, { immediate: true })
</script>

<template>
    <div class="automation-resolved-privacy">
        <h2 class="automation-subtitle">Išspręstų problemų paslėpimas</h2>
        <p class="automation-desc">
            Po nustatyto dienų skaičiaus nuo išsprendimo automatiškai nuimamas viešas skelbimas — problema neberodoma viešame žemėlapyje ir viešoje statistikoje.
        </p>
        <div class="automation-inactive-settings">
            <AppInput
                v-model.number="form.days_after_resolution"
                type="number"
                label="Terminas nuo išsprendimo (dienomis)"
                :min="minDays"
                :max="maxDays"
            />
            <p class="automation-form-hint">{{ daysRangeHint }}</p>
            <p v-if="daysInvalidHint" class="automation-form-error">
                {{ daysInvalidHint }}
            </p>
            <label class="automation-form-checkbox automation-form-checkbox--align-start">
                <span class="automation-checkbox-control">
                    <AppCheckbox v-model="form.is_active" />
                </span>
                <span class="automation-checkbox-label">Aktyvuoti automatinį paslėpimą</span>
            </label>
            <AppButton
                text="Išsaugoti nustatymus"
                variant="primary"
                as-button
                :disabled="!canSave || submitting"
                :loading="submitting"
                @click="submitForm"
            />
        </div>
        <div v-if="resolvedPublicProblemsList?.length" class="automation-inactive-table-block">
            <AppInput
                v-model="search"
                type="search"
                label="Ieškoti problemos"
                placeholder="Numeris, adresas ar aprašymas"
                class="automation-inactive-search"
            />
            <div
                class="automation-table-wrap automation-resolved-table-wrap"
                :class="{ 'automation-inactive-table-scroll': tableScrollable }"
            >
                <table class="automation-table">
                    <thead>
                        <tr>
                            <th class="automation-col-problem">Problema</th>
                            <th>Išspręsta data</th>
                            <th>Paslėpimo data</th>
                            <th class="automation-col-action" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tableRows" :key="row.id">
                            <td
                                class="automation-col-problem"
                                :title="row.problemLabel"
                            >
                                {{ row.problemLabel }}
                            </td>
                            <td>{{ row.resolvedLabel }}</td>
                            <td>{{ row.hideAtLabel }}</td>
                            <td class="automation-col-action">
                                <Link
                                    :href="route('problems.show', row.id)"
                                    class="automation-resolved-view-link"
                                >
                                    Peržiūrėti
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="filteredRows.length === 0" class="automation-empty automation-inactive-no-match">
                Pagal paiešką nieko nerasta.
            </p>
        </div>
        <p v-else class="automation-empty">Nėra viešai rodomų išspręstų problemų.</p>
    </div>
</template>
