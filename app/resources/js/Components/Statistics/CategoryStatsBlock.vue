<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    categories: {
        type: Array,
        default: () => []
    }
})

const maxCount = computed(() => {
    const counts = props.categories.map(c => c.count ?? c.resolved ?? 0).filter(v => typeof v === 'number')
    return Math.max(1, ...counts)
})

function barWidth(category) {
    if (category.resolved !== undefined && category.count === undefined) return category.resolved || 0
    const c = category.count ?? 0
    return maxCount.value ? Math.round((c / maxCount.value) * 100) : 0
}
</script>

<template>
    <div class="category-stats-block">
        <h3 class="section-title">{{ title }}</h3>
        <div v-if="categories.length === 0" class="empty-state">
            <p>Nėra duomenų</p>
        </div>
        <div v-else class="category-list">
            <div v-for="category in categories" :key="category.name" class="category-item">
                <div class="category-info">
                    <span class="category-name">{{ category.name }}</span>
                    <span class="category-percentage">{{ category.count ?? category.resolved ?? 0 }}{{ category.count !== undefined ? ' problemų' : '% Išspręsta' }}</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: barWidth(category) + '%' }"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.category-stats-block {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 20px 0;
    font-family: 'Open Sans', sans-serif;
    color: #333;
}

.empty-state {
    padding: 40px;
    text-align: center;
    color: #999;
}

.category-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.category-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.category-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.category-name {
    font-size: 14px;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

.category-percentage {
    font-size: 14px;
    color: #666;
    font-family: 'Open Sans', sans-serif;
}

.progress-bar {
    height: 10px;
    background: #E0E0E0;
    border-radius: 5px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: #1946A0;
    transition: width 0.3s ease;
    border-radius: 5px;
}
</style>
