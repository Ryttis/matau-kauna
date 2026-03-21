<script setup>
defineProps({
    byCategory: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="reporter-statistics-categories-card">
        <h3 class="reporter-statistics-categories-title">Pagal kategoriją</h3>
        <ul v-if="byCategory.length" class="reporter-statistics-categories-list">
            <li
                v-for="(categoryRow, categoryIndex) in byCategory"
                :key="categoryIndex"
                class="reporter-statistics-categories-item"
            >
                <span class="reporter-statistics-cat-name">{{ categoryRow.category }}</span>
                <span class="reporter-statistics-cat-count">{{ categoryRow.count }}</span>
            </li>
        </ul>
        <p v-else class="reporter-statistics-empty">Nėra duomenų</p>
    </div>
</template>

<style scoped>
.reporter-statistics-categories-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reporter-statistics-categories-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px 0;
}

.reporter-statistics-categories-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.reporter-statistics-categories-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.reporter-statistics-categories-item:last-child {
    border-bottom: none;
}

.reporter-statistics-cat-name {
    color: #333;
}

.reporter-statistics-cat-count {
    font-weight: 600;
    color: #333;
}

.reporter-statistics-empty {
    margin: 0;
    font-size: 14px;
    color: #666;
}
</style>
