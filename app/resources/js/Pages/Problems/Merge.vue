<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { KAUNAS_CENTER } from '@/composables/leafletKaunas'
import { PROBLEM_STATUS } from '@/constants/problemStatus'
import { ICON_ARROW } from '@/constants/icons'

const props = defineProps({
    problem: Object,
    mergedProblems: { type: Array, default: () => [] },
    similarProblems: Array,
    categories: Array,
    selectedCategory: String,
    mapProblems: { type: Array, default: () => [] },
})

const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
const selectedCategory = ref(props.selectedCategory || null)
const selectedProblems = ref([])
const showCategoryDropdown = ref(false)
const categoryDropdownRef = ref(null)
const numberSearchQuery = ref('')
const selectedAddressProblemId = ref('')
const mergeListScrollRef = ref(null)

const mainProblemId = computed(() => props.problem.id)

function mergeProblemLabel(p) {
    const d = (p?.description || '').trim()
    const short = !d ? '' : d.length > 80 ? d.slice(0, 80) + '…' : d
    const nr = p?.internal_number || ''
    return short ? `${short} (Nr. ${nr})` : `Nr. ${nr}`
}

function normalizeInternalNumber(s) {
    return String(s || '').replace(/\s/g, '').toUpperCase()
}

function problemMatchesNumberQuery(problem, rawQuery) {
    const q = normalizeInternalNumber(rawQuery)
    if (!q) return true
    const nr = normalizeInternalNumber(problem?.internal_number)
    return nr.includes(q)
}

const addressSelectOptions = computed(() => {
    const opts = []
    const seen = new Set()

    const pushProblem = (p) => {
        if (!p?.id) return
        const addr = String(p.address || '').trim()
        if (!addr) return
        const id = Number(p.id)
        if (seen.has(id)) return
        seen.add(id)
        const nr = p.internal_number || String(id)
        opts.push({
            value: String(id),
            label: `${addr} — ${nr}`,
        })
    }

    pushProblem(props.problem)
    ;(props.mergedProblems || []).forEach(pushProblem)
    ;(props.similarProblems || []).forEach((s) => {
        pushProblem(s)
        const children = s.merged_problems || s.mergedProblems || []
        children.forEach(pushProblem)
    })

    return opts.sort((a, b) => a.label.localeCompare(b.label, 'lt'))
})

const problemsForMap = computed(() => {
    const fromBackend = props.mapProblems || []
    if (fromBackend.length > 0) return fromBackend
    const list = [props.problem, ...(props.mergedProblems || []), ...(props.similarProblems || [])].filter(Boolean)
    const [centerLat, centerLng] = KAUNAS_CENTER
    return list.map((p, i) => {
        const hasCoords = p.lat != null && p.lng != null
        const offset = i * 0.002
        const desc = p.description || ''
        return {
            id: p.id,
            internal_number: p.internal_number,
            description_preview: desc.slice(0, 200),
            description: desc,
            status: p.status || PROBLEM_STATUS.NEW,
            lat: hasCoords ? Number(p.lat) : centerLat + (offset * 0.1),
            lng: hasCoords ? Number(p.lng) : centerLng + (offset * 0.1),
        }
    })
})

const mainMatchesCategory = computed(() => {
    if (!selectedCategory.value || selectedCategory.value === '') return true
    return (props.problem?.category || '') === selectedCategory.value
})

const selectedProblemIds = computed(() => {
    const selected = selectedProblems.value
    const alwaysShow = mainMatchesCategory.value
        ? [Number(mainProblemId.value), ...(mergedProblemIdsForMap.value || []).map(Number)]
        : []
    if (selected.length === 0) {
        if (!mainMatchesCategory.value) {
            const ids = new Set()
            similarOnly.value.forEach((s) => {
                ids.add(Number(s.id))
                ;(s.merged_problems || s.mergedProblems || []).forEach((m) => ids.add(Number(m.id)))
            })
            return ids
        }
        return new Set(problemsForMap.value.map((p) => Number(p.id)))
    }
    const expanded = new Set(selected.map(Number))
    for (const s of similarOnly.value) {
        if (expanded.has(Number(s.id))) {
            (s.merged_problems || s.mergedProblems || []).forEach((m) => expanded.add(Number(m.id)))
        }
    }
    return new Set([...alwaysShow, ...expanded])
})

const allProblems = computed(() => {
    const problems = [props.problem, ...props.similarProblems]
    return problems.filter((p) => p.status !== PROBLEM_STATUS.UNREGISTERED)
})

const filteredProblems = computed(() => {
    if (!selectedCategory.value || selectedCategory.value === '') {
        return allProblems.value
    }
    return allProblems.value.filter(p => p.category === selectedCategory.value)
})

const mergedIds = computed(() => new Set((props.mergedProblems || []).map(p => p.id)))

const similarOnly = computed(() =>
    filteredProblems.value.filter(
        (p) => p.id !== props.problem.id && !mergedIds.value.has(p.id)
    )
)

const singleListRows = computed(() => {
    const merged = (props.mergedProblems || []).map((p) => ({ type: 'merged', problem: p }))
    const lastInMergedGroup = merged.length === 0
    const main = {
        type: 'main',
        problem: props.problem,
        isInMergedGroup: true,
        isLastInMergedGroup: lastInMergedGroup,
    }
    const mergedRows = merged.map((p, idx) => ({
        ...p,
        isInMergedGroup: true,
        isLastInMergedGroup: idx === merged.length - 1,
        isCurrentGroupEnd: idx === merged.length - 1,
    }))
    const mainWithEnd = { ...main, isCurrentGroupEnd: merged.length === 0 }
    const similarRows = []
    for (const s of similarOnly.value) {
        const children = s.merged_problems || s.mergedProblems || []
        similarRows.push({
            type: 'similar',
            problem: s,
            isInMergedGroup: children.length > 0,
            isLastInMergedGroup: children.length === 0,
        })
        children.forEach((m, idx) => {
            similarRows.push({
                type: 'similar_merged',
                problem: m,
                isInMergedGroup: true,
                isLastInMergedGroup: idx === children.length - 1,
            })
        })
    }
    if (!mainMatchesCategory.value) {
        return similarRows
    }
    return [mainWithEnd, ...mergedRows, ...similarRows]
})

const addressFilterGroupIds = computed(() => {
    if (!selectedAddressProblemId.value) return null
    const tid = Number(selectedAddressProblemId.value)
    if (Number.isNaN(tid)) return null

    for (const s of similarOnly.value) {
        const children = s.merged_problems || s.mergedProblems || []
        const childIds = children.map((m) => Number(m.id))
        if (Number(s.id) === tid || childIds.includes(tid)) {
            return new Set([Number(s.id), ...childIds])
        }
    }
    return new Set([tid])
})

const filteredSingleListRows = computed(() => {
    let rows = singleListRows.value

    const q = numberSearchQuery.value.trim()
    if (q) {
        rows = rows.filter((row) => problemMatchesNumberQuery(row.problem, q))
    }

    const group = addressFilterGroupIds.value
    if (group) {
        rows = rows.filter((row) => group.has(Number(row.problem.id)))
    }

    return rows
})

const listFilterMapIds = computed(() => {
    const hasNumber = numberSearchQuery.value.trim() !== ''
    const hasAddress = Boolean(selectedAddressProblemId.value)
    if (!hasNumber && !hasAddress) return null
    return new Set(filteredSingleListRows.value.map((row) => Number(row.problem.id)))
})

const problemsToShowOnMap = computed(() => {
    let list = problemsForMap.value.filter((p) => selectedProblemIds.value.has(Number(p.id)))
    const filterIds = listFilterMapIds.value
    if (filterIds === null) return list
    return list.filter((p) => filterIds.has(Number(p.id)))
})

watch([numberSearchQuery, selectedAddressProblemId], () => {
    nextTick(() => {
        if (mergeListScrollRef.value) {
            mergeListScrollRef.value.scrollTop = 0
        }
    })
})

const mergedProblemIdsForMap = computed(() =>
    (props.mergedProblems || []).map((p) => p.id)
)

const handleBack = () => {
    router.visit(route('problems.show', props.problem.id))
}

const handleCategoryChange = (category) => {
    selectedCategory.value = category || ''
    showCategoryDropdown.value = false
    selectedProblems.value = []
    numberSearchQuery.value = ''
    selectedAddressProblemId.value = ''

    router.get(route('problems.merge', props.problem.id), {
        category: category || ''
    }, {
        preserveState: false,
        preserveScroll: false,
    })
}

const handleSelectAll = (checked) => {
    if (checked) {
        selectedProblems.value = similarOnly.value.map((p) => p.id)
    } else {
        selectedProblems.value = []
    }
}

const handleSelectItem = (problemId) => {
    if (problemId === props.problem.id) {
        return
    }

    const index = selectedProblems.value.indexOf(problemId)
    if (index > -1) {
        selectedProblems.value.splice(index, 1)
    } else {
        selectedProblems.value.push(problemId)
    }
}

function openProblemView(problemId) {
    router.visit(route('problems.show', problemId))
}

function handleUnmerge(problemId) {
    router.patch(route('problems.unmerge', problemId), {}, { preserveScroll: false })
}

const handleMerge = () => {
    if (selectedProblems.value.length === 0) {
        alert('Pasirinkite bent vieną problemą apjungimui')
        return
    }

    router.post(route('problems.merge.store', props.problem.id), {
        problem_ids: selectedProblems.value,
    }, {
        preserveScroll: false,
        onError: (errors) => {
            alert(errors?.message || 'Nepavyko apjungti')
        },
    })
}

const handleClickOutside = (event) => {
    if (categoryDropdownRef.value && !categoryDropdownRef.value.contains(event.target)) {
        showCategoryDropdown.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <Head :title="`Apjungti problemas - ${problem.internal_number}`" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="merge-page">
            <div class="merge-header">
                <AppBackButton @click="handleBack" />
                <div class="merge-header-content">
                    <div class="merge-category-wrapper" ref="categoryDropdownRef">
                        <button
                            class="merge-category-btn"
                            @click="showCategoryDropdown = !showCategoryDropdown"
                        >
                            <span>{{ selectedCategory || 'Kategorija' }}</span>
                            <img
                                :src="ICON_ARROW"
                                alt="Dropdown"
                                class="merge-dropdown-icon"
                                :class="{ 'merge-dropdown-rotated': showCategoryDropdown }"
                            />
                        </button>
                        <div v-if="showCategoryDropdown" class="merge-category-dropdown">
                            <button
                                class="merge-category-option"
                                :class="{ 'merge-category-option-active': !selectedCategory }"
                                @click="handleCategoryChange('')"
                            >
                                Visos kategorijos
                            </button>
                            <button
                                v-for="category in categories"
                                :key="category"
                                class="merge-category-option"
                                :class="{ 'merge-category-option-active': selectedCategory === category }"
                                @click="handleCategoryChange(category)"
                            >
                                {{ category }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="merge-content">
                <div class="merge-map-section">
                    <div class="merge-map-container">
                        <KaunasProblemsMap
                            :problems="problemsToShowOnMap"
                            :highlighted-problem-id="mainProblemId"
                            :auto-fit-view="false"
                            :focus-highlighted-marker="false"
                            :merged-problem-ids="mergedProblemIdsForMap"
                            marker-class="merge-map-marker"
                            :show-legend="false"
                            @open-problem="openProblemView"
                        />
                    </div>
                </div>

                <div class="merge-problems-section">
                    <div class="merge-filters">
                        <input
                            v-model="numberSearchQuery"
                            type="search"
                            class="merge-search-input"
                            placeholder="Ieškoti pagal problemos Nr."
                            autocomplete="off"
                            aria-label="Ieškoti pagal problemos numerį"
                        />
                        <AppSelect
                            v-model="selectedAddressProblemId"
                            class="merge-address-select"
                            label="Adresas"
                            placeholder=""
                            variant="white"
                            label-position="left"
                        >
                            <option value="">Visi adresai</option>
                            <option
                                v-for="opt in addressSelectOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </AppSelect>
                    </div>

                    <div ref="mergeListScrollRef" class="merge-problems-list-scroll">
                        <div class="merge-problems-list merge-problems-list-single">
                        <div
                            v-if="filteredSingleListRows.length === 0"
                            class="merge-list-empty"
                        >
                            Pagal pasirinktus filtrus problemų nerasta.
                        </div>
                        <div
                            v-for="row in filteredSingleListRows"
                            :key="row.problem.id"
                            class="merge-problem-item"
                            :class="{
                                'merge-problem-item-main': row.type === 'main',
                                'merge-problem-item-merged': row.type === 'merged' || row.type === 'similar_merged',
                                'merge-problem-item-current-group': row.type === 'main' || row.type === 'merged',
                                'merge-problem-item-similar': row.type === 'similar',
                                'merge-problem-item-similar-with-children': row.type === 'similar' && row.isInMergedGroup,
                                'merge-group-inner': row.isInMergedGroup && !row.isLastInMergedGroup,
                                'merge-group-end': row.isLastInMergedGroup,
                                'merge-group-end-current': row.isCurrentGroupEnd,
                            }"
                        >
                            <template v-if="row.type === 'main'">
                                <span class="merge-problem-text">
                                    {{ mergeProblemLabel(row.problem) }} — ši problema
                                </span>
                            </template>
                            <template v-else-if="row.type === 'merged' || row.type === 'similar_merged'">
                                <span class="merge-problem-text merge-problem-text-merged">
                                    {{ mergeProblemLabel(row.problem) }}
                                </span>
                                <button
                                    type="button"
                                    class="merge-unmerge-btn"
                                    @click.stop="handleUnmerge(row.problem.id)"
                                >
                                    Atskirti
                                </button>
                            </template>
                            <template v-else>
                                <label class="merge-problem-label">
                                    <AppCheckbox
                                        :model-value="selectedProblems.includes(row.problem.id)"
                                        :value="row.problem.id"
                                        @change="handleSelectItem(row.problem.id)"
                                    />
                                    <span class="merge-problem-text">
                                        {{ mergeProblemLabel(row.problem) }}
                                    </span>
                                </label>
                            </template>
                        </div>
                        </div>
                    </div>

                    <div class="merge-actions">
                        <label class="merge-select-all-label">
                            <AppCheckbox
                                :model-value="similarOnly.length > 0 && selectedProblems.length === similarOnly.length"
                                @change="handleSelectAll"
                            />
                            <span class="merge-select-all-text">Pasirinkti viską</span>
                        </label>
                        <AppButton
                            text="Apjungti"
                            variant="primary"
                            as-button
                            @click="handleMerge"
                            class="merge-btn"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.merge-page {
    max-width: 1200px;
    margin: 0 auto;
}

.merge-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
}


.merge-header-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.merge-category-wrapper {
    position: relative;
    display: inline-block;
    z-index: 1100;
}

.merge-category-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background-color: var(--color-primary, #1946A0);
    color: var(--color-white, #ffffff);
    border: 1px solid var(--color-primary, #1946A0);
    border-radius: 5px;
    font-size: 14px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    cursor: pointer;
    transition: background-color 0.2s;
}

.merge-category-btn:hover {
    background-color: var(--color-primary-hover, #1a3d95);
}

.merge-dropdown-icon {
    display: block;
    width: 16px;
    height: 16px;
    transition: transform 0.3s ease;
    filter: brightness(0) invert(1);
}


.merge-dropdown-rotated {
    transform: rotate(180deg);
}

.merge-category-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 5px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    min-width: 200px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1100;
    overflow: hidden;
}

.merge-category-option {
    width: 100%;
    padding: 12px 16px;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    color: var(--color-text, #333);
    transition: background-color 0.2s;
}

.merge-category-option:hover {
    background-color: #f6f7f9;
}

.merge-category-option-active {
    background-color: #f6f7f9;
    font-weight: 600;
}

.merge-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.merge-map-section {
    width: 100%;
}

.merge-map-container {
    width: 100%;
    height: 480px;
    min-height: 480px;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 5px;
    overflow: hidden;
}

.merge-map-container :deep(.kaunas-map-wrap) {
    height: 100%;
}

.merge-map-container :deep(.kaunas-map-container) {
    height: 100%;
    min-height: 480px;
}

.merge-problems-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.merge-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
}

.merge-search-input {
    flex: 1;
    min-width: 200px;
    padding: 10px 14px;
    font-size: 14px;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 5px;
    box-sizing: border-box;
}

.merge-search-input::placeholder {
    color: #888;
}

.merge-address-select {
    flex: 1;
    min-width: 260px;
}

.merge-address-select :deep(.app-select) {
    min-width: 220px;
}

.merge-list-empty {
    padding: 20px 16px;
    text-align: center;
    font-size: 14px;
    color: #666;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.merge-list-title {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px 0;
}

.merge-problems-list-single .merge-problem-item-merged {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.merge-problem-text-merged {
    color: #333;
}

.merge-merged-list {
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
}

.merge-problem-item-merged {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.merge-unmerge-btn {
    font-size: 12px;
    color: var(--color-primary, #1946A0);
    background: none;
    border: 1px solid var(--color-primary, #1946A0);
    border-radius: 4px;
    padding: 6px 12px;
    cursor: pointer;
    flex-shrink: 0;
}

.merge-unmerge-btn:hover {
    background: #e8eef7;
}

.merge-problems-list-scroll {
    max-height: calc(10 * 4rem);
    overflow-y: auto;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 5px;
    -webkit-overflow-scrolling: touch;
}

.merge-problems-list-scroll > .merge-problems-list {
    border: none;
    border-radius: 0;
    overflow: visible;
}

.merge-problems-list {
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 5px;
    overflow: hidden;
}

.merge-problem-item {
    padding: 16px;
    border-bottom: 1px solid var(--color-border, #e0e0e0);
    background: var(--color-white, #fff);
    transition: background-color 0.2s;
}

.merge-problem-item:last-child {
    border-bottom: none;
}

.merge-problem-item.merge-group-inner {
    border-bottom: none !important;
}

.merge-problem-item.merge-group-end {
    border-bottom: 2px solid #e0e0e0 !important;
}

.merge-problem-item.merge-group-end-current {
    border-bottom: 2px solid var(--color-primary, #1946A0) !important;
}

.merge-problem-item:hover {
    background-color: #f9f9f9;
}

.merge-problem-item-current-group {
    background-color: #e8eef7;
    border-left: 4px solid var(--color-primary, #1946A0);
}

.merge-problem-item-current-group:hover {
    background-color: #dce5f5;
}

.merge-problem-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    width: 100%;
}

.merge-problem-text {
    font-size: 16px;
    color: var(--color-text, #333);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    line-height: 1.5;
    font-weight: 400;
}

.merge-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    margin-top: 20px;
}

.merge-select-all-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.merge-select-all-text {
    font-size: 16px;
    color: var(--color-text, #333);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-weight: 400;
}

.merge-btn {
    width: 100%;
    max-width: 400px;
}
</style>
