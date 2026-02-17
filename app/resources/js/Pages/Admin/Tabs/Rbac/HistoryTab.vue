<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const activeType = ref('internal')
const search = ref('')
const loading = ref(false)
const errorMessage = ref('')
const data = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const params = new URLSearchParams()
        params.set('type', activeType.value)
        if (search.value.trim()) params.set('search', search.value.trim())
        params.set('page', String(currentPage.value))
        const { data: res } = await axios.get('/admin/login-history?' + params.toString())
        data.value = res.data ?? []
        currentPage.value = res.current_page ?? 1
        lastPage.value = res.last_page ?? 1
        total.value = res.total ?? 0
    } catch (e) {
        errorMessage.value = 'Nepavyko užkrauti istorijos.'
    } finally {
        loading.value = false
    }
}

function formatDate(str) {
    if (!str) return '—'
    return new Date(str).toLocaleString('lt-LT')
}

watch([activeType], () => {
    currentPage.value = 1
    load()
})
watch(search, () => {
    currentPage.value = 1
    load()
})

load()
</script>

<template>
    <div class="history-tab">
        <h3 class="tab-title">Prisijungimų istorija</h3>

        <div class="toolbar">
            <div class="type-toggle">
                <button
                    type="button"
                    :class="['type-btn', { active: activeType === 'internal' }]"
                    @click="activeType = 'internal'; load()"
                >
                    Vidiniai
                </button>
                <button
                    type="button"
                    :class="['type-btn', { active: activeType === 'reporters' }]"
                    @click="activeType = 'reporters'; load()"
                >
                    Pareiškėjai
                </button>
            </div>
            <input
                v-model="search"
                type="text"
                class="search-input"
                placeholder="Ieškoti (vardas, el. paštas)"
            />
        </div>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
        <div v-if="loading" class="loading">Kraunama...</div>

        <div v-else class="table-wrap">
            <table v-if="data.length" class="data-table">
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
                        <td>{{ row.user_name ?? '—' }}</td>
                        <td>{{ row.user_email ?? '—' }}</td>
                        <td>Prisijungimas</td>
                        <td>{{ row.ip ?? '—' }}</td>
                        <td>{{ formatDate(row.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="empty">Nėra įrašų.</p>
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
    border: 1px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
    color: #555;
}
.type-btn:first-child {
    border-radius: 6px 0 0 6px;
}
.type-btn:last-child {
    border-radius: 0 6px 6px 0;
}
.type-btn.active {
    background: #1946a0;
    color: #fff;
    border-color: #1946a0;
}
.search-input {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    min-width: 220px;
}
.error {
    color: #b91c1c;
    font-size: 14px;
    margin: 0 0 12px 0;
}
.loading {
    color: #666;
    font-size: 14px;
}
.table-wrap {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.data-table th,
.data-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e8e8e8;
}
.data-table th {
    font-weight: 600;
    background: #f5f5f5;
}
.empty {
    color: #666;
    padding: 24px 0;
    margin: 0;
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
    border: 1px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    border-radius: 6px;
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-info {
    font-size: 14px;
    color: #666;
}
</style>
