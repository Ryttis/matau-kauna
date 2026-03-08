<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import { useSuccessModal } from '@/composables/useSuccessModal.js'
import { KAUNAS_CENTER } from '@/composables/leafletKaunas'
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

const mainProblemId = computed(() => props.problem.id)

const problemsForMap = computed(() => {
    const fromBackend = props.mapProblems || []
    if (fromBackend.length > 0) return fromBackend
    const list = [props.problem, ...(props.mergedProblems || []), ...(props.similarProblems || [])].filter(Boolean)
    const [centerLat, centerLng] = KAUNAS_CENTER
    return list.map((p, i) => {
        const hasCoords = p.lat != null && p.lng != null
        const offset = i * 0.002
        return {
            id: p.id,
            internal_number: p.internal_number,
            title: p.title,
            status: p.status || 'nauja',
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

const problemsToShowOnMap = computed(() =>
    problemsForMap.value.filter((p) => selectedProblemIds.value.has(Number(p.id)))
)

const allProblems = computed(() => {
    const problems = [props.problem, ...props.similarProblems]
    return problems.filter(p => p.status !== 'neuzregistruota')
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
        const children = s.merged_problems || []
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

const mergedProblemIdsForMap = computed(() =>
    (props.mergedProblems || []).map((p) => p.id)
)

const navigableGroupsForMap = computed(() => {
    const fromMap = problemsForMap.value
    const mainId = Number(mainProblemId.value)
    const mergedSet = new Set((mergedProblemIdsForMap.value || []).map((id) => Number(id)))
    const groups = []
    const main = fromMap.find((p) => Number(p.id) === mainId)
    const mainMerged = fromMap.filter((p) => mergedSet.has(Number(p.id)))
    if (main) groups.push([...mainMerged, main])
    for (const s of similarOnly.value) {
        const children = s.merged_problems || s.mergedProblems || []
        if (children.length === 0) continue
        const childIds = new Set(children.map((m) => Number(m.id)))
        const groupMerged = fromMap.filter((p) => childIds.has(Number(p.id)))
        const parent = fromMap.find((p) => Number(p.id) === Number(s.id))
        if (parent) groups.push([...groupMerged, parent])
    }
    return groups
})

const handleBack = () => {
    router.visit(route('problems.show', props.problem.id))
}

const handleCategoryChange = (category) => {
    selectedCategory.value = category || ''
    showCategoryDropdown.value = false
    selectedProblems.value = []

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
                    <p class="merge-similar-text">Panašūs netoliese:</p>
                </div>
            </div>

            <div class="merge-content">
                <div class="merge-map-section">
                    <div class="merge-map-container">
                        <KaunasProblemsMap
                            :problems="problemsToShowOnMap"
                            :highlighted-problem-id="mainProblemId"
                            :merged-problem-ids="mergedProblemIdsForMap"
                            :navigable-groups="navigableGroupsForMap"
                            marker-class="merge-map-marker"
                            :show-legend="false"
                            @open-problem="openProblemView"
                        />
                    </div>
                </div>

                <div class="merge-problems-section">
                    <div class="merge-problems-list merge-problems-list-single">
                        <div
                            v-for="row in singleListRows"
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
                                    {{ row.problem.title }} (Nr. {{ row.problem.internal_number }}) — ši problema
                                </span>
                            </template>
                            <template v-else-if="row.type === 'merged' || row.type === 'similar_merged'">
                                <span class="merge-problem-text merge-problem-text-merged">
                                    {{ row.problem.title }} (Nr. {{ row.problem.internal_number }})
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
                                        {{ row.problem.title }} (Nr. {{ row.problem.internal_number }})
                                    </span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="merge-actions">
                        <label class="merge-select-all-label">
                            <AppCheckbox
                                :model-value="similarOnly.length > 0 && selectedProblems.length === similarOnly.length"
                                @change="handleSelectAll"
                            />
                            <span class="merge-select-all-text">Pasirinkti visus panašius</span>
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
    border-bottom: 1px solid #e0e0e0;
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
    background-color: #1946A0;
    color: #ffffff;
    border: 1px solid #1946A0;
    border-radius: 5px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: background-color 0.2s;
}

.merge-category-btn:hover {
    background-color: #1a3d95;
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
    font-family: 'Inter', sans-serif;
    color: #333;
    transition: background-color 0.2s;
}

.merge-category-option:hover {
    background-color: #f6f7f9;
}

.merge-category-option-active {
    background-color: #f6f7f9;
    font-weight: 600;
}

.merge-similar-text {
    font-size: 16px;
    color: #333;
    font-family: 'Inter', sans-serif;
    margin: 0;
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
    height: 320px;
    min-height: 320px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
}

.merge-map-container :deep(.kaunas-map-wrap) {
    height: 100%;
}

.merge-map-container :deep(.kaunas-map-container) {
    height: 100%;
    min-height: 320px;
}

.merge-problems-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
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
    color: #1946A0;
    background: none;
    border: 1px solid #1946A0;
    border-radius: 4px;
    padding: 6px 12px;
    cursor: pointer;
    flex-shrink: 0;
}

.merge-unmerge-btn:hover {
    background: #e8eef7;
}

.merge-problems-list {
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
}

.merge-problem-item {
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
    background: #fff;
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
    border-bottom: 2px solid #1946A0 !important;
}

.merge-problem-item:hover {
    background-color: #f9f9f9;
}

.merge-problem-item-current-group {
    background-color: #e8eef7;
    border-left: 4px solid #1946A0;
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
    color: #333;
    font-family: 'Inter', sans-serif;
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
    color: #333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.merge-btn {
    width: 100%;
    max-width: 400px;
}
</style>
