<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import FilterModal from '@/Components/App/Modals/FilterModal.vue'
import SearchFilterBar from '@/Components/App/SearchFilterBar.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemMapStatusLegend from '@/Components/Map/ProblemMapStatusLegend.vue'
import ProblemsMapList from '@/Components/Map/ProblemsMapList.vue'
import { useProblemsMapFilter } from '@/composables/useProblemsMapFilter'
import { useProblemDisplayRows } from '@/composables/useProblemDisplayRows'
import { PUBLIC_MAP_LEGEND_STATUS_ORDER } from '@/constants/domain/problemStatus'

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
    hasActiveFilters,
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

const currentYear = new Date().getFullYear()
const publicMapDateMin = '2000-01-01'
const publicMapDateMax = `${currentYear + 1}-12-31`
</script>

<template>
    <Head title="Pranešimai – Kauno problemos" />
    <div class="landing public-map-page">
        <GuestHeader active-link="reports" />
        <div class="public-map-toolbar">
            <div class="public-map-toolbar-inner">
                <div class="public-map-toolbar-bar">
                    <SearchFilterBar
                        v-model="searchQuery"
                        placeholder="Ieškoti"
                        :filters-active="hasActiveFilters"
                        @filter-click="openFilterModal"
                    />
                </div>
            </div>
        </div>
        <div class="public-map-layout">
            <div class="public-map-wrap">
                <KaunasProblemsMap
                    :problems="problemsToShowOnMap"
                    marker-class="public-map-marker"
                    @open-problem="openProblemView"
                />
                <ProblemMapStatusLegend :status-order="PUBLIC_MAP_LEGEND_STATUS_ORDER" />
            </div>
            <aside class="public-map-list">
                <h3 class="public-map-list-title">Sąrašas</h3>
                <div class="public-map-list-body">
                    <ProblemsMapList
                        :problems="filteredProblems"
                        :display-rows="displayRows"
                        :all-count="allProblems.length"
                        :selected-ids="selectedIds"
                        view-button-variant="internal"
                        card-layout
                        @select="toggle"
                        @select-group="toggleGroup"
                        @open="openProblemView"
                    />
                </div>
            </aside>
        </div>
        <LandingFooter />

        <FilterModal
            :show="showFilterModal"
            variant="publicMap"
            :filters="filterState"
            :categories="categoryOptions"
            :date-min="publicMapDateMin"
            :date-max="publicMapDateMax"
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
}

.public-map-toolbar-bar {
    width: 100%;
    max-width: 400px;
    min-width: 0;
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
    gap: 12px;
    min-height: 0;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    height: 100%;
    background: transparent;
    overflow: hidden;
}

.public-map-wrap :deep(.kaunas-map-wrap) {
    flex: 1;
    min-height: 0;
}

.public-map-wrap :deep(.kaunas-map-container) {
    flex: 1;
    min-height: 0;
    height: 100%;
}

.public-map-list {
    background: var(--color-bg-subtle, #f6f7f9);
    padding: 0.875rem 0.875rem 1rem;
    overflow: hidden;
    min-height: 0;
    min-width: 0;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-radius: 10px;
}

.public-map-list-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    flex-shrink: 0;
    padding: 0 0.125rem;
}

.public-map-list-body {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding-right: 4px;
}

@media (max-width: 767px) {
    .public-map-toolbar-inner {
        padding-left: max(1rem, env(safe-area-inset-left));
        padding-right: max(1rem, env(safe-area-inset-right));
    }

    .public-map-layout {
        gap: 0.75rem;
        padding-bottom: 0;
    }

    .public-map-list {
        max-height: none;
    }

    .public-map-wrap :deep(.kaunas-map-container) {
        flex: none;
        height: clamp(300px, 45vh, 420px);
        min-height: 300px;
    }
}
</style>
