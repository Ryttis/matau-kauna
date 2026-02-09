<script setup>
defineProps({
    description: { type: String, default: '' },
})
</script>

<template>
    <div class="problem-detail-blocks">
        <div class="problem-detail-blocks__description">
            <label class="problem-detail-blocks__label">Aprašymas</label>
            <textarea
                class="problem-detail-blocks__textarea"
                readonly
                :value="description"
            />
        </div>
        <div class="problem-detail-blocks__photo">
            <span class="problem-detail-blocks__photo-text">Nuotrauka</span>
        </div>
    </div>
</template>

<style scoped>
.problem-detail-blocks {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 32px;
    align-items: start;
    margin-top: 8px;
}

.problem-detail-blocks__description {
    height: 150px;
    min-height: 150px;
    max-height: 150px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.problem-detail-blocks__label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    flex-shrink: 0;
}

.problem-detail-blocks__textarea {
    flex: 1;
    min-height: 0;
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: inherit;
    color: #333;
    background: #f9f9f9;
    resize: none;
    box-sizing: border-box;
}

.problem-detail-blocks__photo {
    height: 150px;
    min-height: 150px;
    max-height: 150px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #fff 0% 50%) 50% / 20px 20px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    align-items: center;
    justify-content: center;
}

.problem-detail-blocks__photo-text {
    color: #999;
    font-size: 16px;
}

@media (max-width: 768px) {
    .problem-detail-blocks {
        grid-template-columns: 1fr;
    }
}
</style>
