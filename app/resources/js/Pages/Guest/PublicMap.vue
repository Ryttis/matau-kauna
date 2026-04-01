<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemsMapList from '@/Components/Map/ProblemsMapList.vue'
import { useProblemsMapFilter } from '@/composables/useProblemsMapFilter'
import { useProblemDisplayRows } from '@/composables/useProblemDisplayRows'
import { ICON_FILTER } from '@/constants/ui/icons'

const props = defineProps({
    problems: { type: Array, default: () => [] },
})

const allProblems = computed(() => props.problems || [])

const {
    searchQuery,
    filterState,
    selectedIds,
    categoryOptions,
    filteredProblems,
    problemsToShowOnMap,
    toggle,
    toggleGroup,
    applyFilters,
    clearFilters,
} = useProblemsMapFilter(allProblems)

const groupedForList = computed(() => {
    const list = filteredProblems.value || []
    const mainProblems = list.filter((problem) => !problem.merged_into_problem_id)
    const mergedChildren = list.filter((problem) => problem.merged_into_problem_id)
    const parentIds = new Set(mainProblems.map((problem) => problem.id))
    const mergedByParentId = {}
    mergedChildren.forEach((problem) => {
        const parentId = problem.merged_into_problem_id
        if (!mergedByParentId[parentId]) mergedByParentId[parentId] = []
        mergedByParentId[parentId].push(problem)
    })
    const grouped = mainProblems.map((problem) => ({
        ...problem,
        merged_problems: mergedByParentId[problem.id] || [],
    }))
    const orphanChildren = mergedChildren.filter(
        (problem) => !parentIds.has(problem.merged_into_problem_id)
    )
    orphanChildren.forEach((orphan) => {
        grouped.push({ ...orphan, merged_into_problem_id: null, merged_problems: [] })
    })
    return grouped
})

const { displayRows } = useProblemDisplayRows(groupedForList)

const showFilterModal = ref(false)

function openFilterModal() {
    showFilterModal.value = true
}

function handleFilterApply(filters) {
    applyFilters(filters)
    showFilterModal.value = false
}

function handleFilterClear() {
    clearFilters()
}

function openProblemView(problemId) {
    router.visit(route('reports.show', problemId))
}
</script>

<template>
    <Head title="Pranešimai – Kauno problemos" />
    <div class="landing public-map-page">
        <GuestHeader active-link="reports" />
        <div class="public-map-toolbar">
            <div class="public-map-toolbar-inner">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="public-map-search"
                    placeholder="ieškoti"
                />
                <button
                    type="button"
                    class="public-map-filter-btn"
                    aria-label="Filtruoti"
                    @click="openFilterModal"
                >
                    <img :src="ICON_FILTER" class="public-map-filter-icon" alt="" />
                </button>
            </div>
        </div>
        <div class="public-map-layout">
            <div class="public-map-wrap">
                <KaunasProblemsMap
                    :problems="problemsToShowOnMap"
                    marker-class="public-map-marker"
                    @open-problem="openProblemView"
                />
            </div>
            <aside class="public-map-list">
                <ProblemsMapList
                    :problems="filteredProblems"
                    :display-rows="displayRows"
                    :all-count="allProblems.length"
                    :selected-ids="selectedIds"
                    @select="toggle"
                    @select-group="toggleGroup"
                    @open="openProblemView"
                />
            </aside>
        </div>
        <LandingFooter />

        <FilterModal
            :show="showFilterModal"
            variant="publicMap"
            :filters="filterState"
            :categories="categoryOptions"
            @close="showFilterModal = false"
            @apply="handleFilterApply"
            @clear="handleFilterClear"
        />
    </div>
</template>

<style scoped>
.landing {
    height: 100vh;
    height: 100dvh;
    display: flex;
    flex-direction: column;
    background: #fff;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    overflow: hidden;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.public-map-toolbar {
    flex-shrink: 0;
    background: #fff;
}

.public-map-toolbar-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 12px 1rem;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: 16px;
}

.public-map-search {
    flex: 1;
    min-width: 0;
    max-width: 400px;
    height: 40px;
    border: none;
    border-radius: 20px;
    background: #f6f7f9;
    color: #333;
    padding: 0 16px;
    font-size: 14px;
    box-sizing: border-box;
}

.public-map-search::placeholder {
    color: #999;
}

.public-map-filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    cursor: pointer;
}

.public-map-filter-btn:hover {
    background: #f0f0f0;
}

.public-map-filter-icon {
    width: 24px;
    height: 24px;
}

.public-map-layout {
    flex: 1;
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr);
    grid-template-rows: 1fr;
    gap: 1rem;
    min-height: 0;
    min-width: 0;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 1rem 1rem 0;
    box-sizing: border-box;
}

@media (max-width: 900px) {
    .public-map-layout {
        grid-template-columns: minmax(0, 1fr);
        max-width: none;
        padding-left: max(1rem, env(safe-area-inset-left));
        padding-right: max(1rem, env(safe-area-inset-right));
    }
}

.public-map-wrap {
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    background: #fafafa;
    overflow: hidden;
}

.public-map-wrap :deep(.kaunas-map-container) {
    height: clamp(320px, 55vh, 620px);
    min-height: 320px;
}

.public-map-list {
    background: var(--color-bg-subtle, #f6f7f9);
    padding: 1.25rem 1rem 1.5rem;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

@media (max-width: 767px) {
    .public-map-toolbar-inner {
        padding-left: max(1rem, env(safe-area-inset-left));
        padding-right: max(1rem, env(safe-area-inset-right));
    }

    .public-map-search {
        max-width: none;
    }

    .public-map-layout {
        gap: 0.75rem;
        padding-bottom: 0;
    }

    .public-map-list {
        max-height: none;
    }

    .public-map-wrap :deep(.kaunas-map-container) {
        height: clamp(300px, 45vh, 420px);
        min-height: 300px;
    }
}
</style>
