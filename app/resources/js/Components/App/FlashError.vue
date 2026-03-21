<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const flashError = computed(() => usePage().props.flash?.error || '')
</script>

<template>
    <div v-if="flashError" class="flash-error" role="alert">
        {{ flashError }}
    </div>
</template>

<style scoped>
.flash-error {
    margin-bottom: 16px;
    padding: 12px 16px;
    border-radius: 8px;
    background: #fde8e8;
    color: #c00;
    font-size: 14px;
}
</style>
