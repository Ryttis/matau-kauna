<script setup>
const props = defineProps({
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
            <div v-for="institution in institutions" :key="institution.id" class="institution-item">
                <span>{{ institution.name }}</span>
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
    padding: 10px;
    font-size: 14px;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}
</style>
