<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import { INACTIVITY_DAYS_FALLBACK } from '@/constants/domain/automation'

const props = defineProps({
    inactiveReporterSetting: Object,
    inactiveReportersList: Array,
})

const inactiveReporterSubmitting = ref(false)

const inactiveReporterForm = ref({
    inactivity_days: props.inactiveReporterSetting?.inactivity_days_default ?? INACTIVITY_DAYS_FALLBACK,
    is_active: false,
})

const inactiveReporterMinDays = computed(() => props.inactiveReporterSetting?.inactivity_days_min ?? 30)
const inactiveReporterMaxDays = computed(() => props.inactiveReporterSetting?.inactivity_days_max ?? 3650)

const canSaveInactiveReporterSetting = computed(() => {
    const f = inactiveReporterForm.value
    const minD = inactiveReporterMinDays.value
    const maxD = inactiveReporterMaxDays.value
    return f.inactivity_days != null && f.inactivity_days >= minD && f.inactivity_days <= maxD
})

const inactiveReporterDaysRangeHint = computed(() => {
    const minD = inactiveReporterMinDays.value
    const maxD = inactiveReporterMaxDays.value
    return `Leistina reikšmė: nuo ${minD} iki ${maxD} dienų.`
})

const inactiveReporterDaysInvalidHint = computed(() => {
    if (canSaveInactiveReporterSetting.value) {
        return null
    }
    const raw = inactiveReporterForm.value.inactivity_days
    const minD = inactiveReporterMinDays.value
    const maxD = inactiveReporterMaxDays.value
    if (raw === null || raw === '' || Number.isNaN(Number(raw))) {
        return 'Įveskite neaktyvumo terminą dienomis.'
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

const inactiveReporterSearch = ref('')

const filteredInactiveReporters = computed(() => {
    let rows = (props.inactiveReportersList || []).filter((row) => Number(row.inactive_days || 0) > 0)
    const q = inactiveReporterSearch.value.trim().toLowerCase()
    if (!q) return rows
    return rows.filter((row) => {
        const name = String(row.name || '').toLowerCase()
        const email = String(row.email || '').toLowerCase()
        return name.includes(q) || email.includes(q)
    })
})

const inactiveReporterTableScrollable = computed(
    () => (filteredInactiveReporters.value?.length || 0) > 5
)

function syncInactiveReporterFormFromProps() {
    const s = props.inactiveReporterSetting
    if (!s) return
    inactiveReporterForm.value = {
        inactivity_days: s.inactivity_days ?? s.inactivity_days_default ?? INACTIVITY_DAYS_FALLBACK,
        is_active: s.is_active ?? false,
    }
}

function submitInactiveReporterForm() {
    if (inactiveReporterSubmitting.value) {
        return
    }
    inactiveReporterSubmitting.value = true
    router.put(route('automation.inactiveReporterSetting.update'), {
        inactivity_days: inactiveReporterForm.value.inactivity_days,
        is_active: inactiveReporterForm.value.is_active,
    }, {
        preserveScroll: true,
        onFinish: () => { inactiveReporterSubmitting.value = false },
    })
}

watch(() => props.inactiveReporterSetting, syncInactiveReporterFormFromProps, { immediate: true })
</script>

<template>
    <div>
        <h2 class="automation-subtitle">Nebenaudojamos pareiškėjo paskyros</h2>
        <p class="automation-desc">
            Automatiškai šalinti pareiškėjų paskyras, jei prie paskyros nebuvo prisijungta ilgiau nei nustatytas terminas. Priminimas siunčiamas prieš pusę termino (apvalinant į didesnę pusę).
        </p>
        <div class="automation-inactive-settings">
            <AppInput
                v-model.number="inactiveReporterForm.inactivity_days"
                type="number"
                label="Neaktyvumo terminas (dienomis)"
                :min="inactiveReporterMinDays"
                :max="inactiveReporterMaxDays"
            />
            <p class="automation-form-hint">{{ inactiveReporterDaysRangeHint }}</p>
            <p v-if="inactiveReporterDaysInvalidHint" class="automation-form-error">
                {{ inactiveReporterDaysInvalidHint }}
            </p>
            <label class="automation-form-checkbox">
                <AppCheckbox v-model="inactiveReporterForm.is_active" />
                <span>Aktyvuoti automatinį šalinimą</span>
            </label>
            <AppButton
                text="Išsaugoti nustatymus"
                variant="primary"
                as-button
                :disabled="!canSaveInactiveReporterSetting || inactiveReporterSubmitting"
                :loading="inactiveReporterSubmitting"
                @click="submitInactiveReporterForm"
            />
        </div>
        <div v-if="inactiveReportersList?.length" class="automation-inactive-table-block">
            <AppInput
                v-model="inactiveReporterSearch"
                type="search"
                label="Ieškoti vartotojo"
                placeholder="Vardas arba el. paštas"
                class="automation-inactive-search"
            />
            <div
                class="automation-table-wrap"
                :class="{ 'automation-inactive-table-scroll': inactiveReporterTableScrollable }"
            >
                <table class="automation-table">
                    <thead>
                        <tr>
                            <th>Vartotojas</th>
                            <th>Paskutinis prisijungimas</th>
                            <th>Neaktyvumo dienos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredInactiveReporters" :key="row.id">
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
            <p v-if="filteredInactiveReporters.length === 0" class="automation-empty automation-inactive-no-match">
                Pagal paiešką nieko nerasta.
            </p>
        </div>
        <p v-else class="automation-empty">Nėra pareiškėjų arba jie dar neprisijungė.</p>
    </div>
</template>
