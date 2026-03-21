<script setup>
defineProps({
    description: { type: String, default: '' },
    hideLabel: { type: Boolean, default: false },
})
</script>

<template>
    <div class="problem-detail-blocks" :class="{ 'problem-detail-blocks--fill': hideLabel }">
        <div
            class="problem-detail-blocks__description"
            :class="{ 'problem-detail-blocks__description--no-label': hideLabel }"
        >
            <label v-if="!hideLabel" class="problem-detail-blocks__label">Aprašymas</label>
            <textarea
                class="problem-detail-blocks__textarea"
                readonly
                :value="description"
            />
        </div>
    </div>
</template>

<style scoped>
.problem-detail-blocks {
    margin-top: 0;
    min-width: 0;
    flex: 1 1 260px;
}

.problem-detail-blocks--fill {
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.problem-detail-blocks__description {
    min-height: 120px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.problem-detail-blocks__description--no-label {
    flex: 1 1 auto;
    min-height: 0;
    gap: 0;
    height: 100%;
}

.problem-detail-blocks__label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    flex-shrink: 0;
}

.problem-detail-blocks__textarea {
    flex: 1;
    min-height: 120px;
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: inherit;
    color: #333;
    background: #f9f9f9;
    resize: vertical;
    box-sizing: border-box;
}

.problem-detail-blocks--fill .problem-detail-blocks__textarea {
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
}
</style>
