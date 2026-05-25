<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SearchableSelect from '@/Components/App/SearchableSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import KaunasProblemsMap from '@/Components/Map/KaunasProblemsMap.vue'
import ProblemMapStatusLegend from '@/Components/Map/ProblemMapStatusLegend.vue'
import { useMergePage } from '@/composables/useMergePage'
import { PROBLEM_STATUS, PROBLEM_STATUS_LABELS, getStatusBadgeStyle } from '@/constants/domain/problemStatus'
import '@/Components/Problems/merge-page.css'

const props = defineProps({
    problem: Object,
    mergedProblems: { type: Array, default: () => [] },
    similarProblems: Array,
    categories: Array,
    selectedCategory: String,
    mapProblems: { type: Array, default: () => [] },
})

const mergePage = useMergePage(props)
const {
    showSuccessModal,
    successMessage,
    closeSuccessModal,
    ICON_ARROW,
    MAP_LEGEND_STATUS_ORDER,
    selectedCategory,
    selectedProblems,
    showCategoryDropdown,
    showStatusDropdown,
    numberSearchQuery,
    addressSearchQuery,
    selectedStatuses,
    statusSearchQuery,
    mergeSubmitting,
    unmergeSubmitting,
    mainProblemId,
    mergeProblemLabel,
    categorySelectOptions,
    filteredStatusSelectOptions,
    selectedStatusSummary,
    problemsToShowOnMap,
    mergedProblemIdsForMap,
    markableProblemIdsForMap,
    filteredSingleListRows,
    visibleSelectableProblemIds,
    handleBack,
    handleCategoryChange,
    handleSelectAll,
    handleSelectItem,
    openProblemView,
    toggleStatusDropdown,
    toggleStatusFilter,
    clearStatusFilter,
    handleUnmerge,
    handleMerge,
    handleMarkFromMap,
} = mergePage

function mergeProblemAddress(problem) {
    const address = String(problem?.address || '').trim()
    return address || '-'
}

function mergeProblemCategory(problem) {
    const category = String(problem?.category || '').trim()
    return category || '-'
}

function mergeProblemCreatedDate(problem) {
    if (!problem?.created_at) return '-'
    const date = new Date(problem.created_at)
    if (Number.isNaN(date.getTime())) return '-'
    return date.toLocaleDateString('lt-LT')
}

function mergeProblemStatusLetter(problem) {
    const status = problem?.status || PROBLEM_STATUS.UNREGISTERED
    const label = PROBLEM_STATUS_LABELS[status] || status || '-'
    return String(label).trim().slice(0, 1).toUpperCase()
}

function mergeProblemStatusStyle(problem) {
    return getStatusBadgeStyle(problem?.status || PROBLEM_STATUS.UNREGISTERED)
}
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
                    <div class="merge-category-wrapper" :ref="mergePage.categoryDropdownRef">
                        <button
                            class="merge-category-btn"
                            @click="showCategoryDropdown = !showCategoryDropdown"
                        >
                            <span>{{ selectedCategory || 'Visos kategorijos' }}</span>
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
                        <ProblemMapStatusLegend
                            class="merge-map-legend"
                            :status-order="MAP_LEGEND_STATUS_ORDER"
                        />
                        <KaunasProblemsMap
                            :problems="problemsToShowOnMap"
                            :highlighted-problem-id="mainProblemId"
                            :auto-fit-view="false"
                            :focus-highlighted-marker="false"
                            :merged-problem-ids="mergedProblemIdsForMap"
                            :markable-problem-ids="markableProblemIdsForMap"
                            :selected-problem-ids="selectedProblems"
                            marker-class="merge-map-marker"
                            @open-problem="openProblemView"
                            @mark-problem="handleMarkFromMap"
                        />
                    </div>
                </div>

                <div class="merge-problems-section">
                    <div class="merge-filters">
                        <SearchableSelect
                            :model-value="selectedCategory"
                            class="merge-category-select"
                            label="Kategorija"
                            label-position="left"
                            empty-option-label="Visos kategorijos"
                            search-placeholder="Ieškoti kategorijos…"
                            :options="categorySelectOptions"
                            @update:model-value="handleCategoryChange"
                        />
                        <div class="merge-status-multi" :ref="mergePage.statusDropdownRef">
                            <label class="merge-status-multi__label" for="merge-status-search">Būsena</label>
                            <div class="merge-status-multi__control">
                                <input
                                    id="merge-status-search"
                                    v-model="statusSearchQuery"
                                    class="merge-status-multi__input"
                                    type="text"
                                    :placeholder="selectedStatusSummary"
                                    autocomplete="off"
                                    @focus="showStatusDropdown = true"
                                    @click.stop="showStatusDropdown = true"
                                />
                                <button
                                    v-if="selectedStatuses.length > 0"
                                    type="button"
                                    class="merge-status-multi__clear"
                                    aria-label="Išvalyti būsenas"
                                    @click.stop="clearStatusFilter"
                                >
                                    ×
                                </button>
                                <button
                                    type="button"
                                    class="merge-status-multi__chevron"
                                    aria-label="Rodyti būsenas"
                                    @click.stop="toggleStatusDropdown"
                                >
                                    <img :src="ICON_ARROW" alt="" class="merge-status-multi__chevron-img" />
                                </button>
                                <div v-if="showStatusDropdown" class="merge-status-multi__dropdown">
                                    <button
                                        type="button"
                                        class="merge-status-multi__option merge-status-multi__option--all"
                                        @click.stop="clearStatusFilter"
                                    >
                                        Visos būsenos
                                    </button>
                                    <div
                                        v-for="option in filteredStatusSelectOptions"
                                        :key="option.value"
                                        class="merge-status-multi__option"
                                        :class="{ 'merge-status-multi__option--active': selectedStatuses.includes(option.value) }"
                                    >
                                        <input
                                            class="merge-status-multi__checkbox"
                                            type="checkbox"
                                            :checked="selectedStatuses.includes(option.value)"
                                            :value="option.value"
                                            @change="toggleStatusFilter(option.value)"
                                        />
                                        <span>{{ option.label }}</span>
                                    </div>
                                    <div v-if="filteredStatusSelectOptions.length === 0" class="merge-status-multi__empty">
                                        Nieko nerasta.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <label class="merge-text-filter merge-number-filter">
                            <span class="merge-text-filter__label">Problemos Nr.</span>
                            <input
                                v-model="numberSearchQuery"
                                class="merge-text-filter__input"
                                type="text"
                                placeholder="Visi numeriai"
                                autocomplete="off"
                            />
                        </label>
                        <label class="merge-text-filter merge-address-filter">
                            <span class="merge-text-filter__label">Adresas</span>
                            <input
                                v-model="addressSearchQuery"
                                class="merge-text-filter__input"
                                type="text"
                                placeholder="Visi adresai"
                                autocomplete="off"
                            />
                        </label>
                    </div>

                    <div :ref="mergePage.mergeListScrollRef" class="merge-problems-list-scroll">
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
                                    <div class="merge-problem-content">
                                        <span class="merge-problem-title-row">
                                            <span class="merge-problem-status-initial" :style="mergeProblemStatusStyle(row.problem)">
                                                {{ mergeProblemStatusLetter(row.problem) }}
                                            </span>
                                            <span class="merge-problem-text">
                                                {{ mergeProblemLabel(row.problem) }} - ši problema
                                            </span>
                                        </span>
                                        <span class="merge-problem-meta">
                                            <span class="merge-problem-category-chip">{{ mergeProblemCategory(row.problem) }}</span>
                                            <span>Adresas: {{ mergeProblemAddress(row.problem) }} · Sukurta: {{ mergeProblemCreatedDate(row.problem) }}</span>
                                        </span>
                                    </div>
                                </template>
                                <template v-else-if="row.type === 'merged' || row.type === 'similar_merged'">
                                    <div class="merge-problem-content">
                                        <span class="merge-problem-title-row">
                                            <span class="merge-problem-status-initial" :style="mergeProblemStatusStyle(row.problem)">
                                                {{ mergeProblemStatusLetter(row.problem) }}
                                            </span>
                                            <span class="merge-problem-text merge-problem-text-merged">
                                                {{ mergeProblemLabel(row.problem) }}
                                            </span>
                                        </span>
                                        <span class="merge-problem-meta">
                                            <span class="merge-problem-category-chip">{{ mergeProblemCategory(row.problem) }}</span>
                                            <span>Adresas: {{ mergeProblemAddress(row.problem) }} · Sukurta: {{ mergeProblemCreatedDate(row.problem) }}</span>
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        class="merge-unmerge-btn"
                                        :disabled="unmergeSubmitting"
                                        @click.stop="handleUnmerge(row.problem.id)"
                                    >
                                        Atskirti
                                    </button>
                                </template>
                                <template v-else>
                                    <label
                                        class="merge-problem-label"
                                        :data-merge-selectable-id="row.problem.id"
                                    >
                                        <AppCheckbox
                                            :model-value="selectedProblems.includes(row.problem.id)"
                                            :value="row.problem.id"
                                            @change="handleSelectItem(row.problem.id)"
                                        />
                                        <span class="merge-problem-content">
                                            <span class="merge-problem-title-row">
                                                <span class="merge-problem-status-initial" :style="mergeProblemStatusStyle(row.problem)">
                                                    {{ mergeProblemStatusLetter(row.problem) }}
                                                </span>
                                                <span class="merge-problem-text">
                                                    {{ mergeProblemLabel(row.problem) }}
                                                </span>
                                            </span>
                                            <span class="merge-problem-meta">
                                                <span class="merge-problem-category-chip">{{ mergeProblemCategory(row.problem) }}</span>
                                                <span>Adresas: {{ mergeProblemAddress(row.problem) }} · Sukurta: {{ mergeProblemCreatedDate(row.problem) }}</span>
                                            </span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="merge-actions">
                        <label class="merge-select-all-label">
                            <AppCheckbox
                                :model-value="visibleSelectableProblemIds.length > 0 && visibleSelectableProblemIds.every((id) => selectedProblems.includes(id))"
                                @change="handleSelectAll"
                            />
                            <span class="merge-select-all-text">Pasirinkti viską</span>
                        </label>
                        <AppButton
                            text="Apjungti"
                            variant="primary"
                            as-button
                            :loading="mergeSubmitting"
                            :disabled="mergeSubmitting"
                            class="merge-btn"
                            @click="handleMerge"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
