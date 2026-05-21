<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import AppDataTable from '@/Components/App/AppDataTable.vue'
import AppDatePicker from '@/Components/App/AppDatePicker.vue'
import AppInput from '@/Components/App/AppInput.vue'
import { adminApi } from '@/constants/api/adminApi'

const activeType = ref('internal')
const loading = ref(false)
const errorMessage = ref('')
const data = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const filters = ref({
    date_from: '',
    date_to: '',
    search: '',
    ip: '',
})

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const params = new URLSearchParams()
        params.set('type', activeType.value)
        if (filters.value.date_from) params.set('date_from', filters.value.date_from)
        if (filters.value.date_to) params.set('date_to', filters.value.date_to)
        if (filters.value.search.trim()) params.set('search', filters.value.search.trim())
        if (filters.value.ip.trim()) params.set('ip', filters.value.ip.trim())
        params.set('page', String(currentPage.value))
        const { data: res } = await axios.get(`${adminApi.loginHistory}?${params.toString()}`)
        data.value = res.data ?? []
        currentPage.value = res.current_page ?? 1
        lastPage.value = res.last_page ?? 1
        total.value = res.total ?? 0
    } catch (e) {
        const msg = e?.response?.data?.message
        errorMessage.value = typeof msg === 'string' ? msg : 'Nepavyko užkrauti istorijos.'
    } finally {
        loading.value = false
    }
}

function formatDate(str) {
    if (!str) return '-'
    return new Date(str).toLocaleString('lt-LT')
}

watch([
    activeType,
    () => filters.value.date_from,
    () => filters.value.date_to,
    () => filters.value.search,
    () => filters.value.ip,
], () => {
    currentPage.value = 1
    load()
})

load()
</script>

<template>
    <div class="admin-tab history-tab">
        <h3 class="tab-title">Prisijungimų istorija</h3>

        <div class="toolbar">
            <div class="type-toggle">
                <button
                    type="button"
                    :class="['type-btn', { active: activeType === 'internal' }]"
                    @click="activeType = 'internal'"
                >
                    Vidiniai
                </button>
                <button
                    type="button"
                    :class="['type-btn', { active: activeType === 'reporters' }]"
                    @click="activeType = 'reporters'"
                >
                    Pareiškėjai
                </button>
            </div>
        </div>

        <div class="filters-block">
            <h3 class="block-title">Filtrai ir paieška</h3>
            <div class="filters-row">
                <AppDatePicker
                    v-model="filters.date_from"
                    label="Data nuo"
                    placeholder="Pasirinkite datą"
                    class="filter-date"
                />
                <AppDatePicker
                    v-model="filters.date_to"
                    label="Data iki"
                    placeholder="Pasirinkite datą"
                    :min-date="filters.date_from || undefined"
                    class="filter-date"
                />
                <AppInput
                    v-model="filters.search"
                    type="text"
                    placeholder="Ieškoti (vardas, pavardė, el. paštas)"
                    class="filter-search"
                />
                <AppInput
                    v-model="filters.ip"
                    type="text"
                    placeholder="IP adresas"
                    class="filter-ip"
                />
            </div>
        </div>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <div v-if="loading" class="loading">Kraunama...</div>

        <div v-else>
            <AppDataTable :has-rows="data.length > 0" empty-text="Nėra įrašų.">
                <thead>
                    <tr>
                        <th>Naudotojas</th>
                        <th>El. paštas</th>
                        <th>Veiksmas</th>
                        <th>IP</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data" :key="row.id">
                        <td>{{ row.user_name ?? '-' }}</td>
                        <td>{{ row.user_email ?? '-' }}</td>
                        <td>Prisijungimas</td>
                        <td>{{ row.ip ?? '-' }}</td>
                        <td>{{ formatDate(row.created_at) }}</td>
                    </tr>
                </tbody>
            </AppDataTable>
            <div v-if="lastPage > 1" class="pagination">
                <button
                    type="button"
                    class="page-btn"
                    :disabled="currentPage <= 1"
                    @click="currentPage--; load()"
                >
                    Ankstesnis
                </button>
                <span class="page-info">{{ currentPage }} / {{ lastPage }} (iš viso {{ total }})</span>
                <button
                    type="button"
                    class="page-btn"
                    :disabled="currentPage >= lastPage"
                    @click="currentPage++; load()"
                >
                    Kitas
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.history-tab {
    padding: 8px 0;
}
.tab-title {
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 16px 0;
}
.toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.type-toggle {
    display: flex;
    gap: 0;
}
.type-btn {
    padding: 8px 16px;
    border: 1px solid var(--color-border);
    background: var(--color-white);
    cursor: pointer;
    font-size: 14px;
    color: var(--color-text-muted);
}
.type-btn:first-child {
    border-radius: 6px 0 0 6px;
}
.type-btn:last-child {
    border-radius: 0 6px 6px 0;
}
.type-btn.active {
    background: var(--color-primary);
    color: var(--color-white);
    border-color: var(--color-primary);
}
.filters-block {
    margin-bottom: 24px;
}
.block-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 12px 0;
    padding: 0;
    color: #1a1a1a;
}
.filters-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 16px;
}
.filter-date {
    min-width: 160px;
}
.filter-search {
    min-width: 260px;
}
.filter-ip {
    min-width: 180px;
}
.error {
    color: var(--color-error);
    font-size: 14px;
    margin: 0 0 12px 0;
}
.loading {
    color: var(--color-text-muted);
    font-size: 14px;
}
.pagination {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}
.page-btn {
    padding: 6px 12px;
    font-size: 14px;
    border: 1px solid var(--color-border);
    background: var(--color-white);
    cursor: pointer;
    border-radius: 6px;
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-info {
    font-size: 14px;
    color: var(--color-text-muted);
}
</style>
