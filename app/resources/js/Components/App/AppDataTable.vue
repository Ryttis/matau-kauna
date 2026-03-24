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
    font-size: var(--table-header-font-size);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
}

.app-data-table :deep(th),
.app-data-table :deep(td) {
    padding: 12px 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    vertical-align: middle;
    box-sizing: border-box;
}

.app-data-table :deep(th) {
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    background: var(--table-header-bg);
    color: var(--table-header-text);
}

.app-data-table :deep(tbody tr:hover) {
    background: var(--color-bg-subtle);
}

.app-data-table-empty {
    color: var(--color-text-muted);
    padding: 24px 0;
    margin: 0;
    font-size: var(--table-header-font-size);
}
</style>
