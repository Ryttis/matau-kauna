<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'

const props = defineProps({
    problem: Object,
    similarProblems: Array,
    categories: Array,
    selectedCategory: String,
    mapCenter: Object,
    mapMarkers: Array,
})

const selectedCategory = ref(props.selectedCategory || null)
const selectedProblems = ref([])
const showCategoryDropdown = ref(false)
const categoryDropdownRef = ref(null)

const mainProblemId = computed(() => props.problem.id)

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
        selectedProblems.value = filteredProblems.value
            .filter(p => p.id !== props.problem.id)
            .map(p => p.id)
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

const handleMerge = () => {
    const allSelectedProblems = [mainProblemId.value, ...selectedProblems.value]

    if (allSelectedProblems.length < 2) {
        alert('Pasirinkite bent vieną problemą apjungimui')
        return
    }

    console.log('Apjungti problemas:', allSelectedProblems)
    alert('Apjungimo funkcionalumas bus suderintas vykdant sutartį')
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
                                src="/icons/arrow-icon.png"
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
                    <p class="merge-similar-text">Panašūs netoliese</p>
                </div>
            </div>

            <div class="merge-content">
                <div class="merge-map-section">
                    <div class="merge-map-container">
                        <div class="merge-map-placeholder">
                            <div class="merge-map-label">Žemėlapis</div>
                            <div class="merge-map-dummy">
                                <div class="merge-map-grid"></div>
                                <div class="merge-map-markers">
                                    <div
                                        v-for="(marker, index) in mapMarkers"
                                        :key="marker.id"
                                        class="merge-map-marker"
                                        :class="{ 'merge-map-marker-main': marker.isMain }"
                                        :style="{
                                            left: (50 + (index * 7.5)) + '%',
                                            top: (50 + (index % 2 === 0 ? -13 : 13)) + '%'
                                        }"
                                    >
                                        <span class="merge-map-marker-number">{{ marker.internal_number }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="merge-problems-section">
                    <div class="merge-problems-list">
                        <div
                            v-for="problemItem in filteredProblems"
                            :key="problemItem.id"
                            class="merge-problem-item"
                            :class="{ 'merge-problem-item-main': problemItem.id === problem.id }"
                        >
                            <label class="merge-problem-label">
                                <AppCheckbox
                                    :model-value="problemItem.id === problem.id || selectedProblems.includes(problemItem.id)"
                                    :value="problemItem.id"
                                    :disabled="problemItem.id === problem.id"
                                    @change="handleSelectItem(problemItem.id)"
                                />
                                <span class="merge-problem-text">
                                    {{ problemItem.title }} (Nr. {{ problemItem.internal_number }})
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="merge-actions">
                        <label class="merge-select-all-label">
                            <AppCheckbox
                                :model-value="selectedProblems.length > 0 && selectedProblems.length === filteredProblems.filter(p => p.id !== problem.id).length"
                                @change="handleSelectAll"
                            />
                            <span class="merge-select-all-text">Pasirinkti visus</span>
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
    z-index: 100;
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
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
}

.merge-map-placeholder {
    position: relative;
    width: 100%;
    min-height: 150px;
    background: #f9f9f9;
}

.merge-map-label {
    position: absolute;
    top: 8px;
    left: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
    z-index: 10;
}

.merge-map-dummy {
    width: 100%;
    height: 100%;
    min-height: 150px;
    position: relative;
}

.merge-map-grid {
    width: 100%;
    height: 100%;
    min-height: 150px;
    background-image:
        linear-gradient(to right, #e0e0e0 0.5px, transparent 0.5px),
        linear-gradient(to bottom, #e0e0e0 0.5px, transparent 0.5px);
    background-size: 20px 20px;
    background-color: #f5f5f5;
    position: absolute;
    top: 0;
    left: 0;
}

.merge-map-markers {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 150px;
}

.merge-map-marker {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #F41616;
    border: 2px solid #fff;
    transform: translate(-50%, -50%);
}

.merge-map-marker-main {
    background-color: #1946A0;
}

.merge-map-marker-number {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    color: #333;
    white-space: nowrap;
    font-family: 'Inter', sans-serif;
}

.merge-problems-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
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

.merge-problem-item:hover {
    background-color: #f9f9f9;
}

.merge-problem-item-main {
    background-color: #f0f7ff;
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
