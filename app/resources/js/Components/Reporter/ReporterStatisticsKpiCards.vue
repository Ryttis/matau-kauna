<script setup>
defineProps({
    total: { type: Number, default: 0 },
    solvedCount: { type: Number, default: 0 },
})
</script>

<template>
    <div class="reporter-statistics-kpi">
        <div class="reporter-statistics-card">
            <h3 class="reporter-statistics-card-title">Iš viso problemų</h3>
            <p class="reporter-statistics-card-value">{{ total }}</p>
        </div>
        <div class="reporter-statistics-card">
            <h3 class="reporter-statistics-card-title">Išspręsta</h3>
            <p class="reporter-statistics-card-value">{{ solvedCount ?? 0 }}</p>
        </div>
    </div>
</template>

<style scoped>
.reporter-statistics-kpi {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
    align-items: end;
    max-width: 640px;
}

@media (max-width: 900px) {
    .reporter-statistics-kpi {
        grid-template-columns: 1fr;
        max-width: none;
    }
}

.reporter-statistics-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reporter-statistics-card-title {
    font-size: 14px;
    color: #666;
    margin: 0 0 8px 0;
    font-weight: 400;
    line-height: 1.3;
}

.reporter-statistics-card-value {
    font-size: 28px;
    font-weight: 600;
    color: #333;
    margin: 0;
    line-height: 1.2;
}
</style>
