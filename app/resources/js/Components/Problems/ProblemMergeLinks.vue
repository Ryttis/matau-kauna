<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    mergeTarget: { type: Object, default: null },
    mergedSiblings: { type: Array, default: () => [] },
    mergedChildren: { type: Array, default: () => [] },
    linkRoute: { type: String, default: 'reports.show' },
})
</script>

<template>
    <div v-if="mergeTarget || mergedSiblings?.length" class="problem-merge-links__section problem-merge-links__section--one-line">
        <span class="problem-merge-links__label">Apjungta su:</span>
        <span class="problem-merge-links__inline">
            <template v-if="mergeTarget">
                <Link
                    :href="route(linkRoute, mergeTarget.id)"
                    class="problem-merge-links__link"
                >
                    {{ mergeTarget.internal_number }}
                </Link>
            </template>
            <Link
                v-for="sib in mergedSiblings"
                :key="sib.id"
                :href="route(linkRoute, sib.id)"
                class="problem-merge-links__link"
            >
                {{ sib.internal_number }}
            </Link>
        </span>
    </div>
    <div v-if="mergedChildren?.length" class="problem-merge-links__section problem-merge-links__section--one-line">
        <span class="problem-merge-links__label">Apjungta su:</span>
        <span class="problem-merge-links__inline">
            <Link
                v-for="child in mergedChildren"
                :key="child.id"
                :href="route(linkRoute, child.id)"
                class="problem-merge-links__link"
            >
                {{ child.internal_number }}
            </Link>
        </span>
    </div>
</template>

<style scoped>
.problem-merge-links__section {
    margin-bottom: 20px;
    padding: 12px 16px;
    background: #f0f4ff;
    border-radius: 6px;
}

.problem-merge-links__label {
    margin-right: 8px;
    color: #555;
    font-size: 14px;
}

.problem-merge-links__section--one-line {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 8px;
}

.problem-merge-links__inline {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    align-items: center;
}

.problem-merge-links__link {
    color: #1946a0;
    font-weight: 600;
    text-decoration: none;
}

.problem-merge-links__link:hover {
    text-decoration: none;
}
</style>
