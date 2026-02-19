<script setup>
defineProps({
    emptyText: {
        type: String,
        default: 'Nėra įrašų.',
    },
    hasRows: {
        type: Boolean,
        default: true,
    },
})
</script>

<template>
    <div class="app-data-table-wrap">
        <table v-if="hasRows" class="app-data-table">
            <slot />
        </table>
        <p v-else class="app-data-table-empty">{{ emptyText }}</p>
    </div>
</template>

<style scoped>
.app-data-table-wrap {
    overflow-x: auto;
}

.app-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
}

.app-data-table :deep(th),
.app-data-table :deep(td) {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e8e8e8;
}

.app-data-table :deep(th) {
    font-weight: 600;
    background: #f5f5f5;
    color: #1e1c1c;
}

.app-data-table :deep(tbody tr:hover) {
    background: #f6f7f9;
}

.app-data-table-empty {
    color: #666;
    padding: 24px 0;
    margin: 0;
    font-size: 14px;
}
</style>
