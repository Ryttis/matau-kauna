<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import StatusChangeModal from '@/Components/Problems/StatusChangeModal.vue';
import FilterModal from '@/Components/Problems/FilterModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash-es'
import { PROBLEM_STATUS_LABELS, PROBLEM_STATUS_OPTIONS } from '@/constants/problemStatus'

const props = defineProps({
    problems: Object,
    filters: Object,
    sortBy: String,
    sortDirection: String,
    categories: Array,
    statusOptions: Array,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const categoryFilter = ref(props.filters?.category || '')
const sortBy = ref(props.sortBy || 'created_at')
const sortDirection = ref(props.sortDirection || 'desc')
const selectedProblems = ref([])
const showStatusModal = ref(false)
const selectedProblem = ref(null)
const showFilterModal = ref(false)
const showExportDropdown = ref(false)
const exportWrapper = ref(null)
const exportDropdown = ref(null)
const problemsList = ref([])

const statusLabels = PROBLEM_STATUS_LABELS
const allStatusOptions = props.statusOptions || PROBLEM_STATUS_OPTIONS

const loadProblems = (resetPage = true) => {
    const params = {
        search: search.value || null,
        status: statusFilter.value || null,
        category: categoryFilter.value || null,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }

    if (resetPage) {
        params.page = 1
    }

    router.get(route('dashboard'), params, {
        preserveState: !resetPage,
        preserveScroll: !resetPage,
        replace: resetPage,
        onFinish: () => {
            removePageFromUrl()
        }
    })
}

const loadMoreProblems = () => {
    if (!props.problems?.next_page_url) return

    const url = new URL(props.problems.next_page_url, window.location.origin)
    const page = url.searchParams.get('page')

    router.get(route('dashboard'), {
        page: page,
        search: search.value || null,
        status: statusFilter.value || null,
        category: categoryFilter.value || null,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['problems'],
        onSuccess: (page) => {
            if (page.props.problems?.data) {
                const newItems = page.props.problems.data.slice(0, 6)
                // Patikrinti, ar elementai jau nėra sąraše, kad išvengtume dubliavimosi
                const existingIds = new Set(problemsList.value.map(p => p.id))
                const uniqueNewItems = newItems.filter(item => !existingIds.has(item.id))
                problemsList.value = [...problemsList.value, ...uniqueNewItems]
            }
            setTimeout(() => {
                const currentUrl = new URL(window.location.href)
                currentUrl.searchParams.delete('page')
                window.history.replaceState({}, '', currentUrl.toString())
            }, 0)
        }
    })
}

watch(() => props.problems, (newProblems, oldProblems) => {
    if (!newProblems?.data || !Array.isArray(newProblems.data)) {
        problemsList.value = []
        return
    }

    const currentPage = newProblems.current_page || 1
    const oldPage = oldProblems?.current_page || 1

    if (!oldProblems || currentPage === 1) {
        problemsList.value = newProblems.data.slice(0, 6)
    } else if (currentPage > oldPage) {
        const newItems = newProblems.data.slice(0, 6)
        // Patikrinti, ar elementai jau nėra sąraše, kad išvengtume dubliavimosi
        const existingIds = new Set(problemsList.value.map(p => p.id))
        const uniqueNewItems = newItems.filter(item => !existingIds.has(item.id))
        problemsList.value = [...problemsList.value, ...uniqueNewItems]
    } else if (currentPage < oldPage) {
        problemsList.value = newProblems.data.slice(0, 6)
    }
}, { immediate: true, deep: true })

const handleSearch = debounce(() => {
    problemsList.value = []
    loadProblems(true)
}, 300)

const handleSort = (field) => {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = field
        sortDirection.value = 'desc'
    }
    problemsList.value = []
    loadProblems(true)
}

const handleStatusChange = (status) => {
    if (!selectedProblem.value) return

    router.patch(route('problems.updateStatus', selectedProblem.value.id), {
        status: status
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false
            selectedProblem.value = null
        }
    })
}

const handleModalClose = () => {
    showStatusModal.value = false
    selectedProblem.value = null
}

const openStatusModal = (problem) => {
    selectedProblem.value = problem
    showStatusModal.value = true
}

const handleExport = (scope) => {
    const params = new URLSearchParams({
        search: search.value || '',
        status: statusFilter.value || '',
        category: categoryFilter.value || '',
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
        scope: scope,
        page: props.problems.current_page || 1,
    })

    window.location.href = route('problems.export') + '?' + params.toString()
    showExportDropdown.value = false
}

watch([statusFilter, categoryFilter], () => {
    problemsList.value = []
    loadProblems(true)
})

const handleFilterApply = (filters) => {
    statusFilter.value = filters.status || ''
    showFilterModal.value = false
    problemsList.value = []
    loadProblems(true)
}

const handleFilterClose = () => {
    showFilterModal.value = false
}

const handleClickOutside = (event) => {
    if (exportWrapper.value && !exportWrapper.value.contains(event.target)) {
        showExportDropdown.value = false
    }
}

const removePageFromUrl = () => {
    const url = new URL(window.location.href)
    if (url.searchParams.has('page')) {
        url.searchParams.delete('page')
        window.history.replaceState({}, '', url.toString())
    }
}

onMounted(() => {
    removePageFromUrl()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="search-wrp">
            <input
                v-model="search"
                @input="handleSearch"
                type="text"
                class="search-input"
                placeholder="Ieškoti"
            />
            <img
                src="/icons/filter-icon.png"
                @click="showFilterModal = true"
                class="filter-icon"
            />
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" /></th>
                    <th @click="handleSort('internal_number')" class="sortable">
                        Nr.
                        <span v-if="sortBy === 'internal_number'">
                            {{ sortDirection === 'asc' ? '↑' : '↓' }}
                        </span>
                    </th>
                    <th @click="handleSort('status')" class="sortable">
                        Būsena
                        <span v-if="sortBy === 'status'">
                            {{ sortDirection === 'asc' ? '↑' : '↓' }}
                        </span>
                    </th>
                    <th @click="handleSort('created_at')" class="sortable">
                        Data
                        <span v-if="sortBy === 'created_at'">
                            {{ sortDirection === 'asc' ? '↑' : '↓' }}
                        </span>
                    </th>
                    <th @click="handleSort('title')" class="sortable">
                        Pavadinimas
                        <span v-if="sortBy === 'title'">
                            {{ sortDirection === 'asc' ? '↑' : '↓' }}
                        </span>
                    </th>
                    <th>Kategorija</th>
                    <th>Adresas</th>
                    <th>Aprašymas</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="problem in problemsList" :key="problem.id">
                    <td>
                        <input
                            type="checkbox"
                            :value="problem.id"
                            v-model="selectedProblems"
                        />
                    </td>
                    <td>{{ problem.internal_number }}</td>
                    <td>{{ statusLabels[problem.status] || problem.status }}</td>
                    <td>{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</td>
                    <td>{{ problem.title }}</td>
                    <td>{{ problem.category }}</td>
                    <td>{{ problem.address }}</td>
                    <td>{{ problem.description }}</td>
                </tr>
            </tbody>
        </table>

        <div class="table-footer-wrp" v-if="problems.next_page_url && problemsList.length >= 6">
            <a
                :href="problems.next_page_url"
                @click.prevent="loadMoreProblems"
                class="view-more-link"
            >
                <span>Žiūrėti daugiau</span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    <path d="M8 6L12 10L8 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </a>
        </div>

        <div class="list-navigation">
            <AppButton text="Priskirti problema" />
            <AppButton
                text="Atvaizduoti/keisti busena"
                @click="selectedProblems.length === 1 && openStatusModal(problems.data.find(p => p.id === selectedProblems[0]))"
            />
            <div class="export-wrapper" ref="exportWrapper">
                <AppButton
                    text="Eksportuoti i .xlsx"
                    variant="secondary"
                    @click="showExportDropdown = !showExportDropdown"
                />
                <div v-if="showExportDropdown" class="export-dropdown" ref="exportDropdown">
                    <button @click="handleExport('all')" class="export-option">
                        Eksportuoti visus rezultatus
                    </button>
                    <button @click="handleExport('current_page')" class="export-option">
                        Eksportuoti tik esamą puslapį
                    </button>
                </div>
            </div>
            <a href="#" class="monitor-link">Stebėti</a>
        </div>

        <StatusChangeModal
            :show="showStatusModal"
            :problem="selectedProblem"
            @close="handleModalClose"
            @save="handleStatusChange"
        />

        <FilterModal
            :show="showFilterModal"
            :statusFilter="statusFilter"
            :allStatusOptions="allStatusOptions"
            @close="handleFilterClose"
            @apply="handleFilterApply"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.search-wrp {
    display: flex;
    gap: 40px;
    align-items: center;
}

.search-input {
    width: 340px;
    height: 40px;
    border: none;
    border-radius: 20px;
    background-color: #F6F7F9;
    color: #333;
    padding: 0 20px;
}

.filter-icon {
    display: block;
    margin: auto 0;
    cursor: pointer;
}

table {
    width: 100%;
    margin-top: 40px;
    font-family: 'Inter';
    border-collapse: collapse;
}

th {
    font-size: 12px;
    font-weight: 500;
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
}

th.sortable {
    cursor: pointer;
    user-select: none;
}

th.sortable:hover {
    background-color: #f6f7f9;
}

td {
    padding: 12px 10px;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
}

tbody tr:hover {
    background: #f6f7f9;
}

.table-footer-wrp {
    margin-top: 20px;
    display: flex;
    gap: 20px;
    font-size: 16px;
    align-items: center;
}

.view-more-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: #333;
    font-size: 16px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.view-more-link:hover {
    opacity: 0.8;
}

.view-more-link svg {
    display: block;
    flex-shrink: 0;
}

.list-navigation {
    margin: 40px auto;
    width: 400px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
}

.monitor-link {
    color: #dc3545;
    text-decoration: none;
    font-size: 18px;
    margin-top: 10px;
    text-align: center;
    font-family: 'Inter';
}

.monitor-link:hover {
    text-decoration: underline;
}

.export-wrapper {
    position: relative;
}

.export-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 5px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 5px;
    min-width: 200px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.export-option {
    width: 100%;
    padding: 10px;
    text-align: left;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    border-radius: 4px;
}

.export-option:hover {
    background-color: #f6f7f9;
}

</style>
