<script setup>
defineProps({
    institutions: {
        type: Array,
        default: () => []
    }
})
</script>

<template>
    <div class="institution-results-block">
        <h3 class="section-title">Įstaigų rezultatai</h3>
        <div v-if="institutions.length === 0" class="empty-state">
            <p>Nėra duomenų</p>
        </div>
        <div v-else class="institution-list">
            <div v-for="(institution, idx) in institutions" :key="institution.name || idx" class="institution-item">
                <span>{{ institution.name }}</span>
                <span class="institution-stats">{{ institution.resolved ?? 0 }} / {{ institution.total ?? 0 }} išnagrinėta</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.institution-results-block {
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

.institution-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.institution-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    font-size: 14px;
    color: #333;
    font-family: 'Open Sans', sans-serif;
    min-width: 0;
}

.institution-item > span:first-child {
    min-width: 0;
    flex: 1;
    word-break: break-word;
    line-height: 1.35;
}

.institution-stats {
    font-size: 13px;
    color: #666;
    flex-shrink: 0;
    text-align: right;
    white-space: nowrap;
}

@media (max-width: 400px) {
    .institution-item {
        flex-direction: column;
        align-items: stretch;
    }

    .institution-stats {
        text-align: left;
        white-space: normal;
    }
}
</style>
