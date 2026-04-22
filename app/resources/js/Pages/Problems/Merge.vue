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
    numberSearchQuery,
    selectedAddressProblemId,
    mergeSubmitting,
    unmergeSubmitting,
    mainProblemId,
    mergeProblemLabel,
    addressSelectOptions,
    numberSelectOptions,
    problemsToShowOnMap,
    mergedProblemIdsForMap,
    markableProblemIdsForMap,
    filteredSingleListRows,
    similarOnly,
    handleBack,
    handleCategoryChange,
    handleSelectAll,
    handleSelectItem,
    openProblemView,
    handleUnmerge,
    handleMerge,
    handleMarkFromMap,
} = mergePage
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
                            marker-class="merge-map-marker"
                            @open-problem="openProblemView"
                            @mark-problem="handleMarkFromMap"
                        />
                    </div>
                </div>

                <div class="merge-problems-section">
                    <div class="merge-filters">
                        <SearchableSelect
                            v-model="numberSearchQuery"
                            class="merge-number-select"
                            label="Problemos Nr."
                            label-position="left"
                            empty-option-label="Visi numeriai"
                            search-placeholder="Ieškoti pagal numerį ar aprašymą…"
                            :options="numberSelectOptions"
                        />
                        <SearchableSelect
                            v-model="selectedAddressProblemId"
                            class="merge-address-select"
                            label="Adresas"
                            label-position="left"
                            empty-option-label="Visi adresai"
                            :max-display-length="56"
                            :options="addressSelectOptions"
                        />
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
                                    <span class="merge-problem-text">
                                        {{ mergeProblemLabel(row.problem) }} - ši problema
                                    </span>
                                </template>
                                <template v-else-if="row.type === 'merged' || row.type === 'similar_merged'">
                                    <span class="merge-problem-text merge-problem-text-merged">
                                        {{ mergeProblemLabel(row.problem) }}
                                    </span>
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
